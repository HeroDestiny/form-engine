<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class AuditRecordingContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_creation_records_actor_entity_and_request_context_without_secrets(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        Sanctum::actingAs($user);
        $form = $this->withHeader('User-Agent', 'Audit contract')->postJson('/api/forms', ['name' => 'Auditoria'])
            ->assertCreated()->json('data.form');
        $log = AuditLog::query()->where('action', 'CREATE_FORM')->sole();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame($user->tenant_id, $log->tenant_id);
        $this->assertSame($form['id'], $log->entity_id);
        $this->assertSame('127.0.0.1', $log->ip_address);
        $this->assertSame('Audit contract', $log->user_agent);
        $this->assertArrayNotHasKey('password', $log->metadata);
        $this->assertArrayNotHasKey('token', $log->metadata);
    }

    public function test_audit_failure_rolls_back_form_and_initial_version(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'manager']));
        $this->mock(AuditRecorder::class, function (MockInterface $mock) {
            $mock->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit unavailable'));
        });
        $this->postJson('/api/forms', ['name' => 'Atomicidade'])->assertStatus(500);
        $this->assertDatabaseCount('forms', 0);
        $this->assertDatabaseCount('form_versions', 0);
    }

    public function test_login_success_failure_and_access_denial_are_audited(): void
    {
        $user = User::factory()->create();
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnauthorized();
        $token = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()->json('data.token');
        $this->withToken($token)->postJson('/api/forms', ['name' => 'Forbidden'])->assertForbidden();
        foreach (['LOGIN_FAILED', 'LOGIN_SUCCESS', 'ACCESS_DENIED'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action, 'tenant_id' => $user->tenant_id, 'user_id' => $user->id]);
        }
        $this->assertDatabaseCount('forms', 0);
    }

    public function test_unknown_account_failure_is_recorded_without_assigning_a_tenant(): void
    {
        $this->postJson('/api/auth/login', ['email' => 'unknown@example.com', 'password' => 'wrong'])->assertUnauthorized();
        $this->assertDatabaseHas('audit_logs', ['action' => 'LOGIN_FAILED', 'tenant_id' => null, 'user_id' => null]);
    }

    public function test_audit_log_cannot_be_updated_or_deleted_through_the_model(): void
    {
        $user = User::factory()->create();
        $log = app(AuditRecorder::class)->record('TEST', $user->tenant_id, 'user', $user->id, actorId: $user->id);
        foreach (['update', 'delete'] as $operation) {
            try {
                $operation === 'update' ? $log->update(['action' => 'CHANGED']) : $log->delete();
                $this->fail('Audit mutation was allowed.');
            } catch (\LogicException $exception) {
                $this->assertSame('Logs de auditoria são imutáveis.', $exception->getMessage());
            }
        }
        $this->assertDatabaseHas('audit_logs', ['id' => $log->id, 'action' => 'TEST']);
    }

    public function test_audit_logs_are_immutable_even_when_model_events_are_bypassed(): void
    {
        $user = User::factory()->create();
        $log = app(AuditRecorder::class)->record('TEST', $user->tenant_id, 'user', $user->id, actorId: $user->id);
        foreach (['update', 'delete'] as $operation) {
            try {
                DB::transaction(function () use ($log, $operation) {
                    $query = AuditLog::query()->whereKey($log->id);
                    $operation === 'update' ? $query->update(['action' => 'CHANGED']) : $query->delete();
                });
                $this->fail('SQL audit mutation was allowed.');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('Audit logs are immutable', $exception->getMessage());
            }
        }
        $this->assertDatabaseHas('audit_logs', ['id' => $log->id, 'action' => 'TEST']);
    }

    public function test_tenant_and_user_management_operations_record_audit_without_passwords(): void
    {
        $systemAdmin = User::factory()->create(['role' => 'admin-sistema']);
        Sanctum::actingAs($systemAdmin);
        $tenantId = $this->postJson('/api/admin/tenants', ['name' => 'Audit tenant', 'slug' => 'audit-tenant'])
            ->assertCreated()->json('data.id');
        $this->patchJson('/api/admin/tenants/'.$tenantId.'/status', ['is_active' => false])->assertOk();
        $this->patchJson('/api/admin/tenants/'.$tenantId.'/status', ['is_active' => true])->assertOk();
        $admin = User::factory()->create(['tenant_id' => $tenantId, 'role' => 'admin']);
        Sanctum::actingAs($admin);
        $userId = $this->postJson('/api/tenants/'.$tenantId.'/users', [
            'name' => 'Audit user', 'email' => 'audit@example.com', 'password' => 'Secret123!', 'role' => 'user',
        ])->assertCreated()->json('data.id');
        $this->patchJson("/api/tenants/$tenantId/users/$userId/status", ['is_active' => false])->assertOk();
        $this->patchJson("/api/tenants/$tenantId/users/$userId/status", ['is_active' => true])->assertOk();
        foreach (['CREATE_TENANT', 'UPDATE_TENANT', 'CREATE_USER', 'DEACTIVATE_USER', 'UPDATE_USER'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['tenant_id' => $tenantId, 'action' => $action]);
        }
        $this->assertStringNotContainsString('Secret123!', AuditLog::query()->get()->toJson());
    }
}

<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\FormField;
use App\Models\User;
use App\Repositories\Eloquent\AuditLogRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinalReviewRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_route_identifier_should_return_client_error(): void
    {
        Sanctum::actingAs(User::factory()->create());
        foreach (app('router')->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }
            foreach ($route->parameterNames() as $parameter) {
                foreach (['abc', '999999999999999999999999999999999', '0', '-1', '1.5'] as $identifier) {
                    $uri = preg_replace('/\{\w+\}/', '1', str_replace('{'.$parameter.'}', $identifier, $route->uri()));
                    $this->json($route->methods()[0], '/'.$uri)->assertNotFound()
                        ->assertJsonPath('success', false)->assertJsonPath('data', null)->assertJsonPath('errors', null);
                }
            }
        }
    }

    public function test_password_suffix_must_not_be_ignored(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
        $response = $this->postJson('/api/tenants/'.$admin->tenant_id.'/users', [
            'name' => 'Review Password', 'email' => 'review-password@example.test',
            'password' => str_repeat('a', 72).'original', 'role' => 'user',
        ]);
        $response->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'review-password@example.test']);
        foreach ([str_repeat('a', 72).'different', str_repeat('á', 37), "validpass\0suffix"] as $password) {
            $this->postJson('/api/tenants/'.$admin->tenant_id.'/users', [
                'name' => 'Review Password', 'email' => 'review-password@example.test', 'password' => $password, 'role' => 'user',
            ])->assertUnprocessable()->assertJsonValidationErrors('password');
            $this->postJson('/api/auth/login', ['email' => $admin->email, 'password' => $password])
                ->assertUnprocessable()->assertJsonValidationErrors('password');
        }
    }

    public function test_existing_field_should_be_identified_in_its_audit_details(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
        $data = $this->postJson('/api/forms', ['name' => 'Review Audit'])->assertCreated()->json('data');
        $this->postJson('/api/forms/'.$data['form']['id'].'/versions/'.$data['version']['id'].'/fields', [
            'label' => 'Campo', 'name' => 'campo', 'type' => 'text', 'is_required' => false, 'order' => 1,
        ])->assertCreated();
        $log = AuditLog::query()->where('action', 'ADD_FIELD')->firstOrFail();
        $response = $this->getJson('/api/audit-logs/'.$log->id)->assertOk();
        $this->assertTrue($response->json('data.entity_exists'));
        $field = FormField::findOrFail($log->entity_id);
        $this->putJson('/api/forms/'.$data['form']['id'].'/versions/'.$data['version']['id'].'/fields/'.$field->id, [
            'label' => 'Editado', 'name' => 'campo', 'type' => 'text', 'is_required' => false, 'order' => 1,
        ])->assertOk();
        $this->getJson('/api/audit-logs/'.AuditLog::where('action', 'UPDATE_FIELD')->sole()->id)
            ->assertOk()->assertJsonPath('data.entity_exists', true);
        $foreign = clone $log;
        $foreign->tenant_id = User::factory()->create()->tenant_id;
        $this->assertFalse(app(AuditLogRepository::class)->entityExists($foreign));
        $this->deleteJson('/api/forms/'.$data['form']['id'].'/versions/'.$data['version']['id'].'/fields/'.$field->id)->assertOk();
        $this->getJson('/api/audit-logs/'.$log->id)->assertOk()->assertJsonPath('data.entity_exists', false);
    }

    public function test_distinct_clients_behind_production_proxy_should_not_share_ip_limit(): void
    {
        config(['security.login_attempts_per_minute' => 2]);
        config(['trustedproxy.proxies' => ['172.20.0.2']]);
        for ($i = 1; $i <= 3; $i++) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => '172.20.0.2'])
                ->withHeader('X-Forwarded-For', '198.51.100.'.$i)
                ->postJson('/api/auth/login', ['email' => "review$i@example.test", 'password' => 'invalid']);
            $this->assertSame(401, $response->status(), 'Distinct client '.$i.' was rate limited by proxy IP.');
        }
        $this->assertDatabaseHas('audit_logs', ['action' => 'LOGIN_FAILED', 'ip_address' => '198.51.100.3']);
    }

    public function test_untrusted_client_cannot_bypass_ip_limit_with_forwarded_headers(): void
    {
        config(['security.login_attempts_per_minute' => 2, 'trustedproxy.proxies' => ['172.20.0.2']]);
        for ($i = 1; $i <= 3; $i++) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
                ->withHeader('X-Forwarded-For', '203.0.113.'.$i)
                ->postJson('/api/auth/login', ['email' => "spoof$i@example.test", 'password' => 'invalid']);
            $response->assertStatus($i === 3 ? 429 : 401);
        }
        $this->assertDatabaseHas('audit_logs', ['action' => 'LOGIN_THROTTLED', 'ip_address' => '198.51.100.10']);
    }

    public function test_bcrypt_byte_boundary_accepts_multibyte_password_and_rejects_different_secret(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
        $this->postJson('/api/tenants/'.$admin->tenant_id.'/users', [
            'name' => 'Boundary Password', 'email' => 'boundary@example.test', 'password' => str_repeat('á', 36), 'role' => 'user',
        ])->assertCreated();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', ['email' => 'boundary@example.test', 'password' => str_repeat('á', 36)])->assertOk();
        $this->postJson('/api/auth/login', ['email' => 'boundary@example.test', 'password' => str_repeat('á', 35).'ab'])->assertUnauthorized();
    }
}

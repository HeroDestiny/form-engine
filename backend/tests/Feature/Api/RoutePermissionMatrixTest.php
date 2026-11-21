<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\FormVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoutePermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    private const ALL = ['user', 'manager', 'admin', 'admin-sistema'];

    private const MANAGERS = ['manager', 'admin', 'admin-sistema'];

    private const ADMINS = ['admin', 'admin-sistema'];

    // Every protected API operation, including both form creation aliases.
    public static function routes(): array
    {
        return [
            ['GET', '/auth/me', [], self::ALL, 200],
            ['POST', '/auth/logout', [], self::ALL, 200],
            ['GET', '/admin/tenants', [], ['admin-sistema'], 200],
            ['POST', '/admin/tenants', ['name' => 'Novo tenant', 'slug' => 'new-tenant'], ['admin-sistema'], 201],
            ['PATCH', '/admin/tenants/{tenant}/status', ['is_active' => true], ['admin-sistema'], 200],
            ['GET', '/forms', [], self::ALL, 200],
            ['POST', '/forms', ['name' => 'Novo formulário'], self::MANAGERS, 201],
            ['GET', '/forms/{form}', [], self::ALL, 200],
            ['GET', '/submissions', [], self::ALL, 200],
            ['GET', '/submissions/{submission}', [], self::ALL, 200],
            ['POST', '/submissions/export', ['form_id' => '{form}'], self::MANAGERS, 200],
            ['GET', '/audit-logs', [], self::ADMINS, 200],
            ['GET', '/audit-logs/{log}', [], self::ADMINS, 200],
            ['GET', '/tenants/{tenant}/users', [], self::ADMINS, 200],
            ['POST', '/tenants/{tenant}/users', ['name' => 'Novo usuário', 'email' => 'new@example.com', 'password' => 'Password123!', 'role' => 'user'], self::ADMINS, 201],
            ['PATCH', '/tenants/{tenant}/users/{user}/status', ['is_active' => false], self::ADMINS, 200],
            ['POST', '/tenants/{tenant}/forms', ['name' => 'Novo formulário'], self::MANAGERS, 201],
            ['GET', '/forms/{form}/versions', [], self::ALL, 200],
            ['POST', '/forms/{form}/versions', [], self::MANAGERS, 400], // existing draft
            ['PATCH', '/forms/{form}/status', ['is_active' => false], self::MANAGERS, 200],
            ['GET', '/forms/{form}/versions/{version}/fields', [], self::ALL, 200],
            ['POST', '/forms/{form}/versions/{version}/fields', ['name' => 'novo', 'label' => 'Novo', 'type' => 'text', 'is_required' => false, 'order' => 2], self::MANAGERS, 201],
            ['PUT', '/forms/{form}/versions/{version}/fields/{field}', ['name' => 'nome', 'label' => 'Atualizado', 'type' => 'text', 'is_required' => false, 'order' => 1], self::MANAGERS, 200],
            ['DELETE', '/forms/{form}/versions/{version}/fields/{field}', [], self::MANAGERS, 200],
            ['POST', '/forms/{form}/versions/{version}/publish', [], self::MANAGERS, 200],
            ['GET', '/forms/{form}/drafts', [], self::ALL, 200],
            ['POST', '/forms/{form}/attachments', [], self::ALL, 422],
            ['POST', '/forms/{form}/submit', ['values' => []], self::ALL, 201],
            ['POST', '/forms/{form}/drafts', ['values' => []], self::ALL, 201],
            ['PATCH', '/forms/{form}/drafts/{draft}', ['values' => []], self::ALL, 200],
            ['POST', '/forms/{form}/drafts/{draft}/submit', ['values' => []], self::ALL, 200],
            ['GET', '/submissions/{submission}/attachments/{field}', [], self::ALL, 404],
        ];
    }

    public static function permissions(): iterable
    {
        foreach (self::routes() as [$method, $path, $payload, $roles, $status]) {
            foreach (self::ALL as $role) {
                yield "$role $method $path" => [$role, $method, $path, $payload, in_array($role, $roles, true) ? $status : 403];
            }
        }
    }

    public function test_matrix_covers_every_protected_api_operation(): void
    {
        $covered = array_map(fn ($route) => $route[0].' /api'.$route[1], self::routes());
        $actual = [];
        foreach (app('router')->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/') || ! in_array('auth:sanctum', $route->gatherMiddleware(), true)) {
                continue;
            }
            $uri = str_replace(['{tenantId}', '{formId}', '{versionId}', '{fieldId}', '{submissionId}', '{userId}', '{logId}'],
                ['{tenant}', '{form}', '{version}', '{field}', '{submission}', '{user}', '{log}'], '/'.$route->uri());
            if (str_contains($uri, '/drafts/{submission}')) {
                $uri = str_replace('{submission}', '{draft}', $uri);
            }
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $actual[] = $method.' '.$uri;
            }
        }
        sort($actual);
        sort($covered);
        $this->assertSame($actual, $covered, 'Atualize a matriz ao adicionar ou alterar operações protegidas.');
    }

    private function fixture(User $owner): array
    {
        $form = Form::query()->create(['tenant_id' => $owner->tenant_id, 'created_by' => $owner->id, 'name' => 'Teste', 'is_active' => true]);
        $published = FormVersion::query()->create(['form_id' => $form->id, 'version_number' => 1, 'is_published' => true, 'published_at' => now(), 'created_by' => $owner->id]);
        $version = FormVersion::query()->create(['form_id' => $form->id, 'version_number' => 2, 'is_published' => false, 'created_by' => $owner->id]);
        $field = FormField::query()->create(['form_version_id' => $version->id, 'name' => 'nome', 'label' => 'Nome', 'type' => 'text', 'is_required' => false, 'order' => 1]);
        FormField::query()->create(['form_version_id' => $published->id, 'name' => 'nome', 'label' => 'Nome', 'type' => 'text', 'is_required' => false, 'order' => 1]);
        $submission = FormSubmission::query()->create(['tenant_id' => $owner->tenant_id, 'form_version_id' => $published->id, 'status' => 'submitted', 'submitted_by' => $owner->id, 'submitted_at' => now()]);
        $draft = FormSubmission::query()->create(['tenant_id' => $owner->tenant_id, 'form_version_id' => $published->id, 'status' => 'draft', 'submitted_by' => $owner->id]);
        $other = User::factory()->create(['tenant_id' => $owner->tenant_id]);
        $log = AuditLog::query()->create(['tenant_id' => $owner->tenant_id, 'user_id' => $owner->id, 'action' => 'LOGIN_SUCCESS', 'entity_type' => 'user', 'entity_id' => $owner->id, 'metadata' => []]);

        return ['{tenant}' => $owner->tenant_id, '{form}' => $form->id, '{version}' => $version->id, '{field}' => $field->id,
            '{submission}' => $submission->id, '{draft}' => $draft->id, '{user}' => $other->id, '{log}' => $log->id];
    }

    #[DataProvider('permissions')]
    public function test_each_route_enforces_its_role_and_allows_expected_operations(string $role, string $method, string $path, array $payload, int $status): void
    {
        $actor = User::factory()->create(['role' => $role]);
        $ids = $this->fixture($actor);
        // Real bearer authentication also exercises logout instead of a mocked Sanctum user.
        $token = $actor->createToken('matrix')->plainTextToken;
        foreach ($payload as $key => $value) {
            if (is_string($value) && isset($ids[$value])) {
                $payload[$key] = $ids[$value];
            }
        }
        $this->withToken($token)->json($method, '/api'.strtr($path, $ids), $payload)->assertStatus($status);
        if ($status === 403) {
            $this->assertDatabaseHas('audit_logs', ['action' => 'ACCESS_DENIED', 'user_id' => $actor->id]);
        }
    }

    public function test_all_protected_routes_reject_anonymous_invalid_and_expired_tokens(): void
    {
        $actor = User::factory()->create();
        $ids = $this->fixture($actor);
        $expired = $actor->createToken('expired', ['*'], now()->subMinute())->plainTextToken;
        foreach ([null, 'invalid', $expired] as $token) {
            foreach (self::routes() as [$method, $path]) {
                $this->app['auth']->forgetGuards();
                $this->withHeader('Authorization', $token ? 'Bearer '.$token : '')
                    ->json($method, '/api'.strtr($path, $ids))->assertUnauthorized()->assertJsonPath('success', false);
            }
        }
        $this->assertDatabaseCount('forms', 1);
    }

    public function test_tenant_resources_are_hidden_even_from_system_admin_and_drafts_remain_owner_only(): void
    {
        $foreignOwner = User::factory()->create();
        $foreign = $this->fixture($foreignOwner);
        foreach (self::ALL as $role) {
            $actor = User::factory()->create(['role' => $role]);
            Sanctum::actingAs($actor);
            foreach (self::routes() as [$method, $path, $payload, $roles]) {
                if (! in_array($role, $roles, true) || ! str_contains($path, '{') || str_starts_with($path, '/admin/')) {
                    continue;
                }
                $response = $this->json($method, '/api'.strtr($path, $foreign), $payload);
                $this->assertSame(404, $response->status(), "$role $method $path: ".$response->getContent());
            }
            $sameTenantActor = User::factory()->create(['role' => $role, 'tenant_id' => $foreignOwner->tenant_id]);
            Sanctum::actingAs($sameTenantActor);
            $this->patchJson('/api/forms/'.$foreign['{form}'].'/drafts/'.$foreign['{draft}'], ['values' => []])->assertNotFound();
            $this->postJson('/api/forms/'.$foreign['{form}'].'/drafts/'.$foreign['{draft}'].'/submit', ['values' => []])->assertNotFound();
        }
        $this->assertDatabaseHas('form_submissions', ['id' => $foreign['{draft}'], 'status' => 'draft']);
    }
}

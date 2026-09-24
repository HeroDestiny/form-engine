<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_forms(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/forms');

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_user_cannot_create_forms(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/forms', [
            'name' => 'Cadastro',
        ]);

        $response
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Você não tem permissão para esta ação');
    }

    public function test_manager_can_create_forms(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/forms', [
            'name' => 'Cadastro',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.form.name', 'Cadastro');
    }

    public function test_admin_can_access_audit_logs(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/audit-logs');

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_manager_cannot_access_audit_logs(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/audit-logs');

        $response
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Você não tem permissão para esta ação');
    }

    public function test_admin_can_list_users_from_own_tenant(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        User::factory()->count(2)->create([
            'tenant_id' => $admin->tenant_id,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/tenants/'.$admin->tenant_id.'/users');

        $response
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(3, $response->json('data.users'));
    }

    public function test_manager_cannot_list_users_even_in_own_tenant(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/tenants/'.$manager->tenant_id.'/users');

        $response
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Você não tem permissão para esta ação');
    }

    public function test_tenant_isolation_returns_404_for_other_tenant_resource(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $otherTenant = Tenant::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/tenants/'.$otherTenant->id.'/users');

        $response
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Recurso não encontrado');
    }
}

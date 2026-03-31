<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantUserUseCasesContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_uc01_admin_sistema_can_create_tenant(): void
    {
        $sysAdmin = User::factory()->create(['role' => 'admin-sistema']);
        Sanctum::actingAs($sysAdmin);

        $response = $this->postJson('/api/admin/tenants', [
            'name' => 'Prefeitura Municipal de Exemplo',
            'slug' => 'prefeitura-exemplo',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.slug', 'prefeitura-exemplo')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('message', 'Tenant criado com sucesso');

        $this->assertDatabaseHas('tenants', [
            'slug' => 'prefeitura-exemplo',
            'is_active' => true,
        ]);
    }

    public function test_uc01_rejects_duplicate_slug(): void
    {
        $sysAdmin = User::factory()->create(['role' => 'admin-sistema']);
        Tenant::factory()->create(['slug' => 'slug-existente']);
        Sanctum::actingAs($sysAdmin);

        $response = $this->postJson('/api/admin/tenants', [
            'name' => 'Outro Nome',
            'slug' => 'slug-existente',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_uc01_non_system_admin_cannot_create_tenant(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/tenants', [
            'name' => 'Tenant Bloqueado',
            'slug' => 'tenant-bloqueado',
        ]);

        $response
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Você não tem permissão para esta ação');
    }

    public function test_uc02_admin_sistema_can_toggle_tenant_status(): void
    {
        $sysAdmin = User::factory()->create(['role' => 'admin-sistema']);
        $tenant = Tenant::factory()->create(['is_active' => true]);
        Sanctum::actingAs($sysAdmin);

        $response = $this->patchJson('/api/admin/tenants/'.$tenant->id.'/status', [
            'is_active' => false,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $tenant->id)
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('message', 'Tenant desativado com sucesso');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'is_active' => false,
        ]);
    }

    public function test_uc03_admin_can_create_user_in_own_tenant(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/tenants/'.$admin->tenant_id.'/users', [
            'name' => 'Joao Silva',
            'email' => 'joao@example.com',
            'password' => 'senha1234',
            'role' => 'manager',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tenant_id', $admin->tenant_id)
            ->assertJsonPath('data.role', 'manager')
            ->assertJsonPath('message', 'Usuário criado com sucesso');

        $created = User::query()->where('email', 'joao@example.com')->first();
        $this->assertNotNull($created);
        $this->assertTrue(Hash::check('senha1234', (string) $created?->password));
    }

    public function test_uc03_rejects_duplicate_email_in_same_tenant(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create([
            'tenant_id' => $admin->tenant_id,
            'email' => 'duplicado@example.com',
        ]);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/tenants/'.$admin->tenant_id.'/users', [
            'name' => 'Duplicado',
            'email' => 'duplicado@example.com',
            'password' => 'senha1234',
            'role' => 'user',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_uc03_allows_same_email_in_different_tenants(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otherTenant = Tenant::factory()->create();

        User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'email' => 'repetido@example.com',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/tenants/'.$admin->tenant_id.'/users', [
            'name' => 'Novo Usuario',
            'email' => 'repetido@example.com',
            'password' => 'senha1234',
            'role' => 'user',
        ]);

        $response->assertStatus(201)->assertJsonPath('success', true);
    }

    public function test_uc03_manager_cannot_create_user(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/tenants/'.$manager->tenant_id.'/users', [
            'name' => 'Sem Permissao',
            'email' => 'sem-permissao@example.com',
            'password' => 'senha1234',
            'role' => 'user',
        ]);

        $response
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Você não tem permissão para esta ação');
    }

    public function test_uc04_admin_cannot_deactivate_self(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->patchJson('/api/tenants/'.$admin->tenant_id.'/users/'.$admin->id.'/status', [
            'is_active' => false,
        ]);

        $response
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Você não pode desativar sua própria conta');
    }

    public function test_uc04_admin_can_toggle_other_user_status_in_same_tenant(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create([
            'tenant_id' => $admin->tenant_id,
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $response = $this->patchJson('/api/tenants/'.$admin->tenant_id.'/users/'.$user->id.'/status', [
            'is_active' => false,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('message', 'Usuário desativado com sucesso');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_active' => false,
        ]);
    }

    public function test_uc04_returns_404_for_user_from_other_tenant(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otherUser = User::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson('/api/tenants/'.$admin->tenant_id.'/users/'.$otherUser->id.'/status', [
            'is_active' => false,
        ]);

        $response
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Usuário não encontrado');
    }
}

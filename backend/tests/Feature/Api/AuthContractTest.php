<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_rejects_invalid_credentials_with_generic_message(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'naoexiste@example.com',
            'password' => 'senha-invalida',
        ]);

        $response
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'E-mail ou senha incorretos');
    }

    public function test_login_rejects_inactive_user(): void
    {
        $user = User::factory()->create([
            'email' => 'inativo@example.com',
            'password' => 'password',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Sua conta esta desativada. Entre em contato com o administrador.');
    }

    public function test_login_rejects_inactive_tenant(): void
    {
        $user = User::factory()->create([
            'email' => 'tenant-inativo@example.com',
            'password' => 'password',
        ]);

        $user->tenant()->update(['is_active' => false]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Acesso temporariamente indisponivel. Entre em contato com o suporte.');
    }

    public function test_login_returns_token_for_active_user_and_tenant(): void
    {
        $user = User::factory()->create([
            'email' => 'ativo@example.com',
            'password' => 'password',
            'role' => 'manager',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'ativo@example.com')
            ->assertJsonPath('data.tenant.id', $user->tenant_id)
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_requires_tenant_slug_when_email_exists_in_multiple_tenants(): void
    {
        User::factory()->create([
            'email' => 'duplicado@example.com',
            'password' => 'password',
        ]);

        User::factory()->create([
            'tenant_id' => Tenant::factory()->create()->id,
            'email' => 'duplicado@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'duplicado@example.com',
            'password' => 'password',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['tenant_slug']);
    }

    public function test_login_accepts_tenant_slug_to_select_account(): void
    {
        $tenantA = Tenant::factory()->create(['slug' => 'tenant-a']);
        $tenantB = Tenant::factory()->create(['slug' => 'tenant-b']);

        User::factory()->create([
            'tenant_id' => $tenantA->id,
            'email' => 'duplicado@example.com',
            'password' => 'password',
        ]);

        User::factory()->create([
            'tenant_id' => $tenantB->id,
            'email' => 'duplicado@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'duplicado@example.com',
            'password' => 'password',
            'tenant_slug' => 'tenant-b',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tenant.slug', 'tenant-b')
            ->assertJsonPath('data.user.tenant_id', $tenantB->id);
    }

    public function test_me_requires_authentication(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Não autenticado');
    }

    public function test_me_returns_authenticated_user_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'me@example.com',
            'password' => 'password',
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $token = $loginResponse->json('data.token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'me@example.com')
            ->assertJsonPath('data.tenant.id', $user->tenant_id);
    }

    public function test_logout_requires_authentication(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Não autenticado');
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create([
            'email' => 'logout@example.com',
            'password' => 'password',
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $token = $loginResponse->json('data.token');

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Logout realizado com sucesso');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_protected_form_listing_requires_authentication(): void
    {
        $response = $this->getJson('/api/forms');

        $response
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Não autenticado');
    }
}

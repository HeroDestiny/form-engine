<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantSessionContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_token_is_blocked_when_tenant_is_disabled_and_works_after_reactivation(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        $token = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()->json('data.token');

        $user->tenant()->update(['is_active' => false]);

        $this->withToken($token)->getJson('/api/forms')->assertForbidden()->assertJsonPath('success', false);
        $this->withToken($token)->getJson('/api/auth/me')->assertForbidden();
        $this->withToken($token)->getJson('/api/submissions')->assertForbidden();
        $this->withToken($token)->postJson('/api/forms', ['name' => 'Não deve criar'])->assertForbidden();
        $this->assertDatabaseCount('forms', 0);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $user->tenant()->update(['is_active' => true]);
        $this->withToken($token)->getJson('/api/forms')->assertOk();
    }

    public function test_disabled_tenant_can_logout_and_revoked_token_cannot_be_reused(): void
    {
        $user = User::factory()->create();
        $token = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()->json('data.token');
        $user->tenant()->update(['is_active' => false]);

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/forms')->assertUnauthorized();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_invalid_and_expired_tokens_cannot_access_protected_resources(): void
    {
        $this->withToken('invalid-token')->getJson('/api/forms')->assertUnauthorized();

        $user = User::factory()->create();
        $expiredToken = $user->createToken('expired', ['*'], now()->subMinute())->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($expiredToken)->getJson('/api/forms')->assertUnauthorized();
    }
}

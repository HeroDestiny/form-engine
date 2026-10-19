<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_token_has_configured_lifetime_and_expires_without_activity_renewal(): void
    {
        config(['sanctum.expiration' => 10]);
        $user = User::factory()->create();
        $this->freezeTime();
        $token = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()->assertJsonPath('data.expires_in', 600)->json('data.token');
        $this->assertSame(now()->addMinutes(10)->timestamp, $user->tokens()->first()->expires_at->timestamp);
        $this->travel(9)->minutes();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/forms')->assertOk();
        $this->travel(2)->minutes();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
        $this->withToken($token)->postJson('/api/forms', ['name' => 'Expirado'])->assertUnauthorized();
        $this->assertDatabaseCount('forms', 0);
    }

    public function test_login_limit_has_retry_headers_audit_and_recovers_after_window(): void
    {
        $user = User::factory()->create();
        $credentials = ['email' => $user->email, 'password' => 'wrong'];
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', $credentials)->assertUnauthorized();
        }
        $response = $this->postJson('/api/auth/login', [...$credentials, 'password' => 'password'])
            ->assertStatus(429)->assertJsonPath('success', false)->assertJsonPath('data', null)
            ->assertHeader('Retry-After')->assertHeader('X-RateLimit-Limit', '5');
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'LOGIN_THROTTLED', 'tenant_id' => null, 'user_id' => null]);
        $this->travel(61)->seconds();
        $this->postJson('/api/auth/login', [...$credentials, 'password' => 'password'])->assertOk();
    }

    public function test_changing_email_cannot_bypass_ip_limit_and_changing_ip_cannot_bypass_account_limit(): void
    {
        config(['security.login_attempts_per_minute' => 2]);
        for ($i = 0; $i < 2; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
                ->postJson('/api/auth/login', ['email' => "missing$i@example.com", 'password' => 'wrong'])->assertUnauthorized();
        }
        $this->postJson('/api/auth/login', ['email' => 'third@example.com', 'password' => 'wrong'])->assertStatus(429);
        for ($i = 2; $i < 4; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.$i"])
                ->postJson('/api/auth/login', ['email' => 'target@example.com', 'password' => 'wrong'])->assertUnauthorized();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.4'])
            ->postJson('/api/auth/login', ['email' => 'TARGET@example.com', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_logout_revokes_only_current_session_and_disabled_user_session_expires_naturally(): void
    {
        $user = User::factory()->create();
        $credentials = ['email' => $user->email, 'password' => 'password'];
        $first = $this->postJson('/api/auth/login', $credentials)->assertOk()->json('data.token');
        $second = $this->postJson('/api/auth/login', $credentials)->assertOk()->json('data.token');
        $this->withToken($first)->postJson('/api/auth/logout')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($first)->getJson('/api/auth/me')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $user->update(['is_active' => false]);
        $this->withToken($second)->getJson('/api/auth/me')->assertOk();
        $this->postJson('/api/auth/login', $credentials)->assertForbidden();
        $this->travel((int) config('sanctum.expiration') + 1)->minutes();
        $this->app['auth']->forgetGuards();
        $this->withToken($second)->getJson('/api/forms')->assertUnauthorized();
    }

    public function test_malformed_email_is_validated_and_invalid_credentials_do_not_reveal_account(): void
    {
        $user = User::factory()->create();
        $this->postJson('/api/auth/login', ['email' => ['invalid'], 'password' => 'x'])->assertUnprocessable();
        $known = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnauthorized();
        $unknown = $this->postJson('/api/auth/login', ['email' => 'missing@example.com', 'password' => 'wrong'])->assertUnauthorized();
        $this->assertSame($known->json(), $unknown->json());
    }
}

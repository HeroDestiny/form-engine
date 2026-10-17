<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantSlugRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_zero_slug_does_not_authenticate_account_from_another_tenant(): void
    {
        Tenant::factory()->create(['slug' => '0']);
        $other = User::factory()->create();
        $this->postJson('/api/auth/login', [
            'email' => $other->email, 'password' => 'password', 'tenant_slug' => '0',
        ])->assertUnauthorized();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_zero_slug_selects_correct_account_even_when_email_is_shared(): void
    {
        $tenant = Tenant::factory()->create(['slug' => '0']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        User::factory()->create(['email' => $user->email]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email, 'password' => 'password', 'tenant_slug' => '0',
        ])->assertOk()->assertJsonPath('data.user.id', $user->id)->assertJsonPath('data.tenant.slug', '0');
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $user->id]);
    }

    public function test_zero_slug_authenticates_its_unique_account(): void
    {
        $tenant = Tenant::factory()->create(['slug' => '0']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->postJson('/api/auth/login', [
            'email' => $user->email, 'password' => 'password', 'tenant_slug' => '0',
        ])->assertOk()->assertJsonPath('data.tenant.id', $tenant->id);
    }

    public function test_hundred_character_slug_can_be_created_and_used_to_select_account(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin-sistema']));
        $slug = str_repeat('a', 100);
        $tenant = $this->postJson('/api/admin/tenants', ['name' => 'Boundary Tenant', 'slug' => $slug])
            ->assertCreated()->json('data');
        $user = User::factory()->create(['tenant_id' => $tenant['id']]);
        User::factory()->create(['email' => $user->email]);
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/auth/login', [
            'email' => $user->email, 'password' => 'password', 'tenant_slug' => $slug,
        ])->assertOk()->assertJsonPath('data.user.id', $user->id)->assertJsonPath('data.tenant.slug', $slug);
    }

    public function test_oversized_slugs_are_rejected_in_creation_and_login_without_persisting_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin-sistema']);
        Sanctum::actingAs($admin);
        foreach ([101, 255, 256] as $length) {
            $slug = str_repeat('b', $length);
            $this->postJson('/api/admin/tenants', ['name' => 'Oversized Tenant', 'slug' => $slug])
                ->assertUnprocessable()->assertJsonValidationErrors('slug');
            $this->postJson('/api/auth/login', [
                'email' => $admin->email, 'password' => 'password', 'tenant_slug' => $slug,
            ])->assertUnprocessable()->assertJsonValidationErrors('tenant_slug');
            $this->assertDatabaseMissing('tenants', ['slug' => $slug]);
        }
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'CREATE_TENANT']);
    }
}

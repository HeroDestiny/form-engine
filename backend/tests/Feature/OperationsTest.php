<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_and_cli_check_real_database_cache_and_temporary_file(): void
    {
        $route = collect(app('router')->getRoutes())->first(fn ($route) => $route->uri() === 'ready');
        $this->assertNotContains('web', $route->gatherMiddleware());
        $this->getJson('/ready')->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson(['ready' => true, 'checks' => ['database' => true, 'cache' => true, 'temporary_storage' => true]]);
        $this->artisan('app:health')->assertExitCode(0);
        $this->get('/up')->assertOk();
    }

    public function test_readiness_fails_without_exposing_exception_details_or_secrets(): void
    {
        Cache::shouldReceive('put')->andThrow(new \RuntimeException('private-password-host'));
        Cache::shouldReceive('forget')->andReturn(true);
        $this->getJson('/ready')->assertStatus(503)->assertJsonPath('ready', false)->assertJsonPath('checks.cache', false)
            ->assertDontSee('private-password-host');
        $this->artisan('app:health')->assertExitCode(1);
    }

    public function test_production_check_rejects_development_and_accepts_required_configuration(): void
    {
        $this->artisan('app:production-check')->assertExitCode(1);
        $this->app->instance('env', 'production');
        config(['app.debug' => false, 'app.url' => 'https://forms.example.test', 'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'database.default' => 'pgsql', 'database.connections.pgsql.password' => str_repeat('p', 20),
            'cache.default' => 'redis', 'database.redis.cache.password' => str_repeat('r', 20), 'logging.default' => 'stderr']);
        $this->artisan('app:production-check')->assertExitCode(0);
        // Restore the test connection before RefreshDatabase cleanup.
        config(['database.default' => ($_SERVER['TEST_DB_PROFILE'] ?? '') === 'isolated-postgres' ? 'pgsql' : 'sqlite']);
    }
}

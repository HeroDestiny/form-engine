<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckProduction extends Command
{
    protected $signature = 'app:production-check';

    protected $description = 'Valida requisitos locais de configuração de produção; não imprime segredos.';

    public function handle(): int
    {
        $key = (string) config('app.key');
        $decoded = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : false;
        $checks = [
            'environment' => app()->environment('production'),
            'debug_disabled' => config('app.debug') === false,
            'https_url' => filter_var(config('app.url'), FILTER_VALIDATE_URL) !== false
                && parse_url(config('app.url'), PHP_URL_SCHEME) === 'https',
            'app_key' => is_string($decoded) && strlen($decoded) === 32,
            'postgresql' => config('database.default') === 'pgsql',
            'database_password' => strlen((string) config('database.connections.pgsql.password')) >= 16,
            'shared_cache' => config('cache.default') === 'redis',
            'redis_password' => strlen((string) config('database.redis.cache.password')) >= 16,
            'stderr_logs' => config('logging.default') === 'stderr',
            'token_expiration' => (int) config('sanctum.expiration') > 0,
        ];
        foreach ($checks as $name => $passed) {
            $this->line($name.': '.($passed ? 'OK' : 'FAIL'));
        }

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}

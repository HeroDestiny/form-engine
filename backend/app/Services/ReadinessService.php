<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ReadinessService
{
    public function checks(): array
    {
        $checks = [];
        $probes = [
            'database' => fn () => DB::select('SELECT 1'),
            'cache' => function (): void {
                $key = 'readiness:'.bin2hex(random_bytes(12));
                try {
                    Cache::put($key, 'ok', 15);
                    if (Cache::get($key) !== 'ok') {
                        throw new RuntimeException('Cache round trip failed.');
                    }
                } finally {
                    Cache::forget($key);
                }
            },
            'temporary_storage' => function (): void {
                $stream = tmpfile();
                if ($stream === false) {
                    throw new RuntimeException('Temporary storage unavailable.');
                }
                try {
                    if (fwrite($stream, 'ok') !== 2) {
                        throw new RuntimeException('Temporary storage is not writable.');
                    }
                } finally {
                    fclose($stream);
                }
            },
        ];
        foreach ($probes as $name => $probe) {
            try {
                $probe();
                $checks[$name] = true;
            } catch (Throwable) {
                $checks[$name] = false;
                Log::warning('Readiness check failed.', ['check' => $name]);
            }
        }

        return $checks;
    }
}

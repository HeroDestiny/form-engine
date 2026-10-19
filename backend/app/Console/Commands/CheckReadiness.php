<?php

namespace App\Console\Commands;

use App\Services\ReadinessService;
use Illuminate\Console\Command;

class CheckReadiness extends Command
{
    protected $signature = 'app:health';

    protected $description = 'Verifica banco, cache e armazenamento temporário sem revelar configuração.';

    public function handle(ReadinessService $service): int
    {
        $checks = $service->checks();
        $this->line(json_encode($checks, JSON_THROW_ON_ERROR));

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}

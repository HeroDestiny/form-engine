<?php

namespace App\Http\Controllers;

use App\Services\ReadinessService;
use Illuminate\Http\JsonResponse;

class ReadinessController extends Controller
{
    public function __invoke(ReadinessService $service): JsonResponse
    {
        $checks = $service->checks();
        $ready = ! in_array(false, $checks, true);

        return response()->json(['ready' => $ready, 'checks' => $checks], $ready ? 200 : 503)
            ->header('Cache-Control', 'no-store');
    }
}

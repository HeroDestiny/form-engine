<?php

namespace App\Http\Middleware;

use App\Services\AuditRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditDeniedRequests
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (in_array($response->getStatusCode(), [403, 404], true) && $request->user() && ! $request->is('api/auth/login')) {
            $this->audit->record('ACCESS_DENIED', $request->user()->tenant_id, 'api', null, [
                'path' => $request->path(), 'method' => $request->method(), 'status' => $response->getStatusCode(),
            ]);
        }

        return $response;
    }
}

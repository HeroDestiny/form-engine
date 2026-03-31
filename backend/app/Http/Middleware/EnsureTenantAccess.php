<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = (int) $request->route('tenantId');
        $user = $request->user();

        if (! $user || $user->tenant_id !== $tenantId) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Recurso não encontrado',
                'errors' => null,
            ], 404);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Exceptions\BusinessException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()->tenant()->where('is_active', true)->exists()) {
            throw new BusinessException('Acesso temporariamente indisponivel. Entre em contato com o suporte.', 'forbidden');
        }

        return $next($request);
    }
}

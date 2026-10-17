<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LimitRequestBody
{
    public function handle(Request $request, Closure $next): Response
    {
        if ((int) $request->server('CONTENT_LENGTH') > 2097152 || strlen($request->getContent()) > 2097152) {
            return response()->json(['success' => false, 'data' => null,
                'message' => 'O corpo da requisição excede o limite de 2 MiB.', 'errors' => null], 413);
        }

        return $next($request);
    }
}

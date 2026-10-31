<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LimitRequestBody
{
    public function handle(Request $request, Closure $next): Response
    {
        $limit = $request->is('api/forms/*/attachments') ? 10485760 : 2097152;
        $limitMib = (int) ($limit / 1048576);

        if ((int) $request->server('CONTENT_LENGTH') > $limit || strlen($request->getContent()) > $limit) {
            return response()->json(['success' => false, 'data' => null,
                'message' => "O corpo da requisição excede o limite de {$limitMib} MiB.", 'errors' => null], 413);
        }

        return $next($request);
    }
}

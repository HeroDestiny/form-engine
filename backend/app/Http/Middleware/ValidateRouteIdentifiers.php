<?php

namespace App\Http\Middleware;

use App\Exceptions\NotFoundException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateRouteIdentifiers
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach ($request->route()->parameters() as $name => $value) {
            if (str_ends_with($name, 'Id') && filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                throw new NotFoundException('Recurso não encontrado');
            }
        }

        return $next($request);
    }
}

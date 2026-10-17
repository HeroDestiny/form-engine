<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use JsonException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ValidateApiPayload
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isJson() && $request->getContent() !== '') {
            try {
                $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
                // Empty arrays remain compatible with clients sending an empty payload.
                if (! is_array($payload) || ($payload !== [] && array_is_list($payload))) {
                    throw new BadRequestHttpException('O corpo JSON deve ser um objeto.');
                }
            } catch (JsonException) {
                throw new BadRequestHttpException('JSON inválido.');
            }
        }

        $this->validateText($request->all());

        return $next($request);
    }

    private function validateText(mixed $value, string $path = ''): void
    {
        if (is_string($value) && str_contains($value, "\0")) {
            throw ValidationException::withMessages([$path ?: 'payload' => 'O texto não pode conter caracteres nulos.']);
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (is_string($key) && str_contains($key, "\0")) {
                    throw ValidationException::withMessages(['payload' => 'Os nomes dos campos não podem conter caracteres nulos.']);
                }
                $this->validateText($item, $path === '' ? (string) $key : $path.'.'.$key);
            }
        }
    }
}

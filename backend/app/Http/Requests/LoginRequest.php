<?php

namespace App\Http\Requests;

class LoginRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'tenant_slug' => ['nullable', 'string', 'regex:/^[a-z0-9-]+$/'],
        ];
    }
}

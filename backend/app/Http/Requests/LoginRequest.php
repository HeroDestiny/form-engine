<?php

namespace App\Http\Requests;

class LoginRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:255'],
            'tenant_slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/'],
        ];
    }
}

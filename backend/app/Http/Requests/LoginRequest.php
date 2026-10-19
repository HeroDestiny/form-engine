<?php

namespace App\Http\Requests;

use App\Rules\BcryptPassword;

class LoginRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', new BcryptPassword],
            'tenant_slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/'],
        ];
    }
}

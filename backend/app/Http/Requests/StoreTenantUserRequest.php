<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreTenantUserRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->where(fn ($query) => $query->where('tenant_id', (int) $this->route('tenantId'))),
            ],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['admin', 'manager', 'user'])],
        ];
    }
}

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
                'max:254',
                Rule::unique('users', 'email')->where(fn ($query) => $query->where('tenant_id', (int) $this->route('tenantId'))),
            ],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'role' => ['required', Rule::in(['admin', 'manager', 'user'])],
        ];
    }
}

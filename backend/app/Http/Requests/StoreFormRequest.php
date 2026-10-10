<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreFormRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:3',
                'max:255',
                Rule::unique('forms', 'name')->where(fn ($query) => $query->where('tenant_id', (int) ($this->route('tenantId') ?? $this->user()->tenant_id))),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

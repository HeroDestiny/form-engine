<?php

namespace App\Http\Requests;

class PaginatedQueryRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1', 'max:2147483647'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', 20);
    }

    protected function identifierRules(): array
    {
        return ['nullable', 'integer', 'min:1', 'max:2147483647'];
    }
}

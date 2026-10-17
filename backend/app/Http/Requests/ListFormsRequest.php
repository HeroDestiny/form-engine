<?php

namespace App\Http\Requests;

class ListFormsRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'in:name,created_at'],
            'order' => ['nullable', 'in:asc,desc'],
            'scope' => ['nullable', 'in:available,all'],
        ];
    }
}

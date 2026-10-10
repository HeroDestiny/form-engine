<?php

namespace App\Http\Requests;

class ListSubmissionsRequest extends PaginatedQueryRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'status' => ['nullable', 'in:submitted,draft'],
            'form_id' => $this->identifierRules(),
            'version_id' => $this->identifierRules(),
            'user_id' => $this->identifierRules(),
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('date_from') ? ['after_or_equal:date_from'] : [])],
        ];
    }
}

<?php

namespace App\Http\Requests;

class ExportSubmissionsRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'form_id' => ['required', 'integer'],
            'version_ids' => ['nullable', 'array'],
            'version_ids.*' => ['integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'format' => ['nullable', 'in:csv'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ExportSubmissionsRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'form_id' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'version_ids' => ['nullable', 'array', 'list', 'max:'.config('limits.export_versions')],
            'version_ids.*' => ['integer', 'min:1', 'max:2147483647', 'distinct'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            'format' => ['nullable', 'in:csv'],
            'separator' => ['sometimes', Rule::in([',', ';'])],
        ];
    }
}

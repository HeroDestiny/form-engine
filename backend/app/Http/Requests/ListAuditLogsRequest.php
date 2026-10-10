<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Validation\Validator;

class ListAuditLogsRequest extends PaginatedQueryRequest
{
    public function rules(): array
    {
        $dateRules = ['nullable', 'string', 'date', 'regex:/^\d{4}-\d{2}-\d{2}(?:T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2}))?$/'];

        return [
            ...parent::rules(),
            'action' => ['nullable', 'string', 'max:100'],
            'entity_type' => ['nullable', 'string', 'max:100'],
            'entity_id' => $this->identifierRules(),
            'user_id' => $this->identifierRules(),
            'date_from' => $dateRules,
            'date_to' => $dateRules,
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('date_from') || ! $this->filled('date_to')
                || $validator->errors()->has('date_from') || $validator->errors()->has('date_to')) {
                return;
            }
            $from = CarbonImmutable::parse($this->input('date_from'), 'UTC')->utc();
            $to = CarbonImmutable::parse($this->input('date_to'), 'UTC')->utc();
            if (strlen($this->input('date_to')) === 10) {
                $to = $to->endOfDay();
            }
            if ($to->lessThan($from)) {
                $validator->errors()->add('date_to', 'A data final deve ser maior ou igual à data inicial.');
            }
        });
    }
}

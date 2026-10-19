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
            $dates = [];
            foreach (['date_from', 'date_to'] as $field) {
                if (! $this->filled($field) || $validator->errors()->has($field)) {
                    continue;
                }
                $date = CarbonImmutable::parse($this->input($field), 'UTC')->utc();
                if ($date->year < 1 || $date->year > 9999) {
                    $validator->errors()->add($field, 'A data em UTC deve estar entre os anos 0001 e 9999.');
                } else {
                    $dates[$field] = $date;
                }
            }
            if (! isset($dates['date_from'], $dates['date_to'])) {
                return;
            }
            $from = $dates['date_from'];
            $to = $dates['date_to'];
            if (strlen($this->input('date_to')) === 10) {
                $to = $to->endOfDay();
            }
            if ($to->lessThan($from)) {
                $validator->errors()->add('date_to', 'A data final deve ser maior ou igual à data inicial.');
            }
        });
    }
}

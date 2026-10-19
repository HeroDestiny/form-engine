<?php

namespace App\Http\Requests;

use App\Rules\CalendarDate;

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
            'date_from' => ['nullable', new CalendarDate],
            'date_to' => ['nullable', new CalendarDate, ...($this->filled('date_from') ? ['after_or_equal:date_from'] : [])],
        ];
    }
}

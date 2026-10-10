<?php

namespace App\Http\Requests;

class UpdateStatusRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }
}

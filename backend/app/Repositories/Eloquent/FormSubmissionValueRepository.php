<?php

namespace App\Repositories\Eloquent;

use App\Models\FormSubmissionValue;
use App\Repositories\Contracts\FormSubmissionValueRepositoryInterface;

class FormSubmissionValueRepository implements FormSubmissionValueRepositoryInterface
{
    public function create(array $data): FormSubmissionValue
    {
        return FormSubmissionValue::query()->create($data);
    }
}

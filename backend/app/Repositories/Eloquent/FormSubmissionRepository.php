<?php

namespace App\Repositories\Eloquent;

use App\Models\FormSubmission;
use App\Repositories\Contracts\FormSubmissionRepositoryInterface;

class FormSubmissionRepository implements FormSubmissionRepositoryInterface
{
    public function create(array $data): FormSubmission
    {
        return FormSubmission::query()->create($data);
    }
}

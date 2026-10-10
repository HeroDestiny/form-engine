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

    public function deleteForSubmission(int $submissionId): void
    {
        FormSubmissionValue::query()->where('form_submission_id', $submissionId)->delete();
    }
}

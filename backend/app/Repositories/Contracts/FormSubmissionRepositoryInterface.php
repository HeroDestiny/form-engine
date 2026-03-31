<?php

namespace App\Repositories\Contracts;

use App\Models\FormSubmission;

interface FormSubmissionRepositoryInterface
{
    public function create(array $data): FormSubmission;
}

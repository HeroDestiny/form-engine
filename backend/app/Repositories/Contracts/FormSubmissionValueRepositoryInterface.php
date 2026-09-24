<?php

namespace App\Repositories\Contracts;

use App\Models\FormSubmissionValue;

interface FormSubmissionValueRepositoryInterface
{
    public function create(array $data): FormSubmissionValue;
}

<?php

namespace App\Http\Requests;

use App\Services\Forms\SubmissionService;

class UpdateDraftRequest extends SaveDraftRequest
{
    protected function prepareForValidation(): void
    {
        $this->context = app(SubmissionService::class)->draftContext($this->user(), (int) $this->route('formId'), (int) $this->route('submissionId'));
    }

    public function rules(): array
    {
        return [...parent::rules(), 'values' => ['present', 'array', 'max:'.config('limits.fields_per_version')], 'version_id' => ['prohibited']];
    }
}

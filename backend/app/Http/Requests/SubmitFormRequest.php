<?php

namespace App\Http\Requests;

use App\Services\Forms\SubmissionService;

class SubmitFormRequest extends ApiRequest
{
    protected array $context;

    protected function prepareForValidation(): void
    {
        $this->context = app(SubmissionService::class)->submissionContext(
            (int) $this->user()->tenant_id,
            (int) $this->route('formId'),
            $this->isDraft(),
            filter_var($this->input('version_id'), FILTER_VALIDATE_INT) ?: null,
        );
    }

    public function rules(): array
    {
        return ['version_id' => ['nullable', 'integer', 'min:1'], 'values' => ['sometimes', 'array'], ...app(SubmissionService::class)->buildRules($this->context['version'], $this->isDraft())];
    }

    public function submissionContext(): array
    {
        return $this->context;
    }

    public function values(): array
    {
        return $this->validated('values', []);
    }

    protected function isDraft(): bool
    {
        return false;
    }
}

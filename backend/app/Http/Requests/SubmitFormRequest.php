<?php

namespace App\Http\Requests;

use App\Services\Forms\SubmissionService;

class SubmitFormRequest extends ApiRequest
{
    private array $context;

    protected function prepareForValidation(): void
    {
        $this->context = app(SubmissionService::class)->submissionContext(
            (int) $this->user()->tenant_id,
            (int) $this->route('formId'),
            $this->isDraft(),
        );
    }

    public function rules(): array
    {
        return ['values' => ['sometimes', 'array'], ...app(SubmissionService::class)->buildRules($this->context['version'], $this->isDraft())];
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

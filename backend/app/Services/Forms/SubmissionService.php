<?php

namespace App\Services\Forms;

use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\FormVersion;
use App\Repositories\Contracts\FormSubmissionRepositoryInterface;
use App\Repositories\Contracts\FormSubmissionValueRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SubmissionService
{
    public function __construct(
        private readonly FormSubmissionRepositoryInterface $formSubmissionRepository,
        private readonly FormSubmissionValueRepositoryInterface $formSubmissionValueRepository,
    ) {}

    public function buildRules(FormVersion $version, bool $partial = false): array
    {
        $rules = [];

        foreach ($version->fields as $field) {
            $fieldRules = [];

            if (! $partial && $field->is_required) {
                $fieldRules[] = 'required';
            }

            if (in_array($field->type, ['text', 'textarea', 'email', 'select', 'radio'], true)) {
                $fieldRules[] = 'string';
            }
            if ($field->type === 'number') {
                $fieldRules[] = 'numeric';
            }
            if ($field->type === 'date') {
                $fieldRules[] = 'date';
            }
            if (in_array($field->type, ['select', 'radio'], true) && is_array($field->options)) {
                $fieldRules[] = Rule::in(array_column($field->options, 'value'));
            }

            if ($partial) {
                if (! empty($fieldRules)) {
                    $rules['values.'.$field->name] = ['sometimes', ...$fieldRules];
                }

                continue;
            }

            $rules['values.'.$field->name] = $fieldRules;
        }

        return $rules;
    }

    public function createSubmitted(int $tenantId, int $userId, FormVersion $version, array $values): FormSubmission
    {
        return DB::transaction(function () use ($tenantId, $userId, $version, $values) {
            $submission = $this->formSubmissionRepository->create([
                'tenant_id' => $tenantId,
                'form_version_id' => $version->id,
                'status' => 'submitted',
                'submitted_by' => $userId,
                'submitted_at' => now(),
            ]);

            $this->persistValues($submission, $version, $values);

            return $submission;
        });
    }

    public function createDraft(int $tenantId, int $userId, ?FormVersion $version, array $values): FormSubmission
    {
        return DB::transaction(function () use ($tenantId, $userId, $version, $values) {
            $draft = $this->formSubmissionRepository->create([
                'tenant_id' => $tenantId,
                'form_version_id' => (int) $version?->id,
                'status' => 'draft',
                'submitted_by' => $userId,
                'submitted_at' => null,
            ]);

            if ($version) {
                $this->persistValues($draft, $version, $values);
            }

            return $draft;
        });
    }

    private function persistValues(FormSubmission $submission, FormVersion $version, array $values): void
    {
        foreach ($version->fields as $field) {
            if (! array_key_exists($field->name, $values)) {
                continue;
            }

            $this->formSubmissionValueRepository->create([
                'form_submission_id' => $submission->id,
                'form_field_id' => $field->id,
                'value' => (string) $values[$field->name],
            ]);
        }
    }

    public function mapValueForField(FormField $field, mixed $value): mixed
    {
        if (in_array($field->type, ['select', 'radio'], true) && is_array($field->options)) {
            $selected = collect($field->options)->firstWhere('value', $value);

            return $selected['label'] ?? $value;
        }

        return $value;
    }
}

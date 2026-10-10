<?php

namespace App\Services\Forms;

use App\Exceptions\BusinessException;
use App\Exceptions\NotFoundException;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\FormVersion;
use App\Models\User;
use App\Repositories\Contracts\FormRepositoryInterface;
use App\Repositories\Contracts\FormSubmissionRepositoryInterface;
use App\Repositories\Contracts\FormSubmissionValueRepositoryInterface;
use App\Repositories\Contracts\FormVersionRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SubmissionService
{
    public function __construct(
        private readonly FormSubmissionRepositoryInterface $formSubmissionRepository,
        private readonly FormSubmissionValueRepositoryInterface $formSubmissionValueRepository,
        private readonly FormRepositoryInterface $formRepository,
        private readonly FormVersionRepositoryInterface $formVersionRepository,
        private readonly SubmissionCsvService $csvService,
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

    public function createDraft(int $tenantId, int $userId, FormVersion $version, array $values): FormSubmission
    {
        return DB::transaction(function () use ($tenantId, $userId, $version, $values) {
            $draft = $this->formSubmissionRepository->create([
                'tenant_id' => $tenantId,
                'form_version_id' => $version->id,
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

    public function listForUser(User $actor, array $filters, int $perPage): LengthAwarePaginator
    {
        if (! in_array($actor->role, ['manager', 'admin'], true)) {
            unset($filters['user_id']);
        }
        $paginator = $this->formSubmissionRepository->paginateForTenant(
            (int) $actor->tenant_id,
            $actor->role === 'user' ? (int) $actor->id : null,
            $filters,
            $perPage,
        );

        $submissions = $paginator->getCollection()->map(function (FormSubmission $submission) {
            $owner = $submission->submitter;

            return [
                'id' => $submission->id,
                'form' => [
                    'id' => $submission->version->form->id,
                    'name' => $submission->version->form->name,
                ],
                'version' => [
                    'id' => $submission->version->id,
                    'version_number' => $submission->version->version_number,
                ],
                'user' => [
                    'id' => $submission->submitted_by,
                    'name' => $owner?->name,
                    'email' => $owner?->email,
                ],
                'submitted_at' => $submission->submitted_at,
                'fields_count' => $submission->values->count(),
            ];
        })->values();

        $paginator->setCollection($submissions);

        return $paginator;
    }

    public function listDrafts(User $actor, int $formId): Collection
    {
        return $this->formSubmissionRepository->listDrafts((int) $actor->tenant_id, (int) $actor->id, $formId);
    }

    public function details(User $actor, int $submissionId): array
    {
        $submission = $this->formSubmissionRepository->findInTenant((int) $actor->tenant_id, $submissionId);
        if (! $submission || ($actor->role === 'user' && $submission->submitted_by !== (int) $actor->id)) {
            throw new NotFoundException('Submissão não encontrada');
        }
        $owner = $submission->submitter;

        $values = $submission->version->fields->sortBy('order')->map(function (FormField $field) use ($submission) {
            $value = $submission->values->firstWhere('form_field_id', $field->id)?->value;
            $item = [
                'field_id' => $field->id,
                'label' => $field->label,
                'name' => $field->name,
                'type' => $field->type,
                'value' => $value,
                'is_required' => $field->is_required,
            ];

            if (in_array($field->type, ['select', 'radio'], true) && is_array($field->options)) {
                $selected = collect($field->options)->firstWhere('value', $value);
                $item['value_label'] = $selected['label'] ?? $value;
            }

            return $item;
        })->values();

        return [
            'submission' => [
                'id' => $submission->id,
                'status' => $submission->status,
                'submitted_at' => $submission->submitted_at,
                'created_at' => $submission->created_at,
            ],
            'form' => [
                'id' => $submission->version->form->id,
                'name' => $submission->version->form->name,
                'description' => $submission->version->form->description,
            ],
            'version' => [
                'id' => $submission->version->id,
                'version_number' => $submission->version->version_number,
                'published_at' => $submission->version->published_at,
            ],
            'user' => $owner ? [
                'id' => $owner->id,
                'name' => $owner->name,
                'email' => $owner->email,
            ] : null,
            'values' => $values,
        ];
    }

    public function submissionContext(int $tenantId, int $formId, bool $partial = false): array
    {
        $form = $this->formRepository->findInTenant($tenantId, $formId);
        if (! $form) {
            if ($partial) {
                throw new BusinessException('Este formulário não está mais disponível');
            }
            throw new NotFoundException;
        }
        if (! $form->is_active) {
            throw new BusinessException('Este formulário não está mais disponível', $partial ? 'invalid_operation' : 'form_unavailable');
        }
        $version = $this->formVersionRepository->findLatestPublishedByForm($formId);
        if (! $version) {
            throw new BusinessException('Versão não pode ser publicada');
        }

        return ['form' => $form, 'version' => $version];
    }

    public function exportForUser(User $actor, array $filters): array
    {
        if (! in_array($actor->role, ['manager', 'admin'], true)) {
            throw new BusinessException('Você não tem permissão para esta ação', 'forbidden');
        }
        $form = $this->formRepository->findInTenant((int) $actor->tenant_id, (int) $filters['form_id']);
        if (! $form) {
            throw new BusinessException('Nenhuma submissão encontrada para exportar');
        }
        $submissions = $this->formSubmissionRepository->listForExport((int) $actor->tenant_id, $form->id, $filters);
        if ($submissions->isEmpty()) {
            throw new BusinessException('Nenhuma submissão encontrada para exportar');
        }

        return $this->csvService->render($form->id, $submissions);
    }
}

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
use App\Services\AuditRecorder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SubmissionService
{
    public function __construct(
        private readonly FormSubmissionRepositoryInterface $formSubmissionRepository,
        private readonly FormSubmissionValueRepositoryInterface $formSubmissionValueRepository,
        private readonly FormRepositoryInterface $formRepository,
        private readonly FormVersionRepositoryInterface $formVersionRepository,
        private readonly SubmissionCsvService $csvService,
        private readonly AuditRecorder $audit,
    ) {}

    public function buildRules(FormVersion $version, bool $partial = false): array
    {
        $rules = [];

        foreach ($version->fields as $field) {
            $fieldRules = $partial ? ['sometimes', 'nullable'] : [$field->is_required ? 'required' : 'nullable'];

            if (in_array($field->type, ['text', 'textarea', 'email', 'select', 'radio'], true)) {
                $fieldRules[] = 'string';
            }
            if ($field->type === 'number') {
                $fieldRules[] = 'numeric';
            }
            if ($field->type === 'email') {
                $fieldRules[] = 'email';
            }
            if ($field->type === 'date') {
                $fieldRules[] = 'date';
            }
            if (in_array($field->type, ['select', 'radio'], true) && is_array($field->options)) {
                $fieldRules[] = Rule::in(array_column($field->options, 'value'));
            }

            if ($field->type === 'checkbox') {
                $fieldRules[] = 'array';
                $fieldRules[] = 'list';
                $rules['values.'.$field->name.'.*'] = ['string', Rule::in(array_column($field->options ?? [], 'value'))];
            }

            $rules['values.'.$field->name] = $fieldRules;
        }

        return $rules;
    }

    public function createSubmitted(int $tenantId, int $userId, FormVersion $version, array $values): FormSubmission
    {
        return DB::transaction(function () use ($tenantId, $userId, $version, $values) {
            $this->ensureWritableVersion($tenantId, $version);
            $submission = $this->formSubmissionRepository->create([
                'tenant_id' => $tenantId,
                'form_version_id' => $version->id,
                'status' => 'submitted',
                'submitted_by' => $userId,
                'submitted_at' => now(),
            ]);

            $this->persistValues($submission, $version, $values);
            $this->audit->record('SUBMIT_FORM', $tenantId, 'form_submission', $submission->id, ['version_id' => $version->id], $userId);

            return $submission;
        });
    }

    public function createDraft(int $tenantId, int $userId, FormVersion $version, array $values): FormSubmission
    {
        return DB::transaction(function () use ($tenantId, $userId, $version, $values) {
            $this->ensureWritableVersion($tenantId, $version);
            $draft = $this->formSubmissionRepository->create([
                'tenant_id' => $tenantId,
                'form_version_id' => $version->id,
                'status' => 'draft',
                'submitted_by' => $userId,
                'submitted_at' => null,
            ]);

            $this->persistValues($draft, $version, $values);
            $this->audit->record('SAVE_DRAFT', $tenantId, 'form_submission', $draft->id, ['version_id' => $version->id], $userId);

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
                'value' => SubmissionFieldValue::encode($field, $values[$field->name]),
            ]);
        }
    }

    public function listForUser(User $actor, array $filters, int $perPage): LengthAwarePaginator
    {
        if (! in_array($actor->role, ['manager', 'admin', 'admin-sistema'], true)) {
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
        if (! $this->formRepository->findInTenant((int) $actor->tenant_id, $formId)) {
            throw new NotFoundException;
        }

        return $this->formSubmissionRepository->listDrafts((int) $actor->tenant_id, (int) $actor->id, $formId);
    }

    public function draftContext(User $actor, int $formId, int $submissionId): array
    {
        $draft = $this->formSubmissionRepository->findInTenant((int) $actor->tenant_id, $submissionId);
        if (! $draft || $draft->submitted_by !== $actor->id || $draft->status !== 'draft' || $draft->version->form_id !== $formId) {
            throw new NotFoundException('Rascunho não encontrado');
        }
        if (! $draft->version->form->is_active) {
            throw new BusinessException('Este formulário não está mais disponível', 'form_unavailable');
        }

        return ['form' => $draft->version->form, 'version' => $draft->version, 'draft' => $draft];
    }

    public function saveExistingDraft(User $actor, int $formId, int $submissionId, array $values, bool $finalize = false): FormSubmission
    {
        return DB::transaction(function () use ($actor, $formId, $submissionId, $values, $finalize) {
            $draft = $this->formSubmissionRepository->lockDraft((int) $actor->tenant_id, (int) $actor->id, $formId, $submissionId);
            if (! $draft) {
                throw new NotFoundException('Rascunho não encontrado');
            }
            $context = $this->draftContext($actor, $formId, $submissionId);
            $this->ensureWritableVersion((int) $actor->tenant_id, $context['version']);
            // Revalidate against the stored version inside the transaction.
            $validated = Validator::make(['values' => $values], $this->buildRules($context['version'], ! $finalize))->validate();
            $this->formSubmissionValueRepository->deleteForSubmission($draft->id);
            $this->persistValues($draft, $context['version'], $validated['values'] ?? []);
            if ($finalize) {
                $draft = $this->formSubmissionRepository->update($draft, ['status' => 'submitted', 'submitted_at' => now()]);
            }
            $this->audit->record($finalize ? 'SUBMIT_FORM' : 'SAVE_DRAFT', $actor->tenant_id, 'form_submission', $draft->id, ['version_id' => $draft->form_version_id, 'from_draft' => true], $actor->id);

            return $draft;
        });
    }

    public function details(User $actor, int $submissionId): array
    {
        $submission = $this->formSubmissionRepository->findInTenant((int) $actor->tenant_id, $submissionId);
        if (! $submission || ($actor->role === 'user' && $submission->submitted_by !== (int) $actor->id)) {
            throw new NotFoundException('Submissão não encontrada');
        }
        $owner = $submission->submitter;

        $values = $submission->version->fields->sortBy('order')->map(function (FormField $field) use ($submission) {
            $value = SubmissionFieldValue::decode($field, $submission->values->firstWhere('form_field_id', $field->id)?->value);
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

    public function submissionContext(int $tenantId, int $formId, bool $partial = false, ?int $versionId = null): array
    {
        $form = $this->formRepository->findInTenant($tenantId, $formId);
        if (! $form) {
            throw new NotFoundException;
        }
        if (! $form->is_active) {
            throw new BusinessException('Este formulário não está mais disponível', $partial ? 'invalid_operation' : 'form_unavailable');
        }
        $version = $versionId === null
            ? $this->formVersionRepository->findLatestPublishedByForm($formId)
            : $this->formVersionRepository->findInTenant($tenantId, $formId, $versionId);
        if ($versionId !== null && (! $version || ! $version->is_published)) {
            throw new NotFoundException('Versão publicada não encontrada');
        }
        if (! $version) {
            throw new BusinessException('Versão não pode ser publicada');
        }

        return ['form' => $form, 'version' => $version];
    }

    public function exportForUser(User $actor, array $filters): array
    {
        if (! in_array($actor->role, ['manager', 'admin', 'admin-sistema'], true)) {
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

        $csv = $this->csvService->render($form->id, $submissions, $filters['separator'] ?? ',');
        $this->audit->record('EXPORT_DATA', $actor->tenant_id, 'form', $form->id, [
            'records_count' => $submissions->count(),
            'filters' => array_intersect_key($filters, array_flip(['version_ids', 'date_from', 'date_to', 'separator'])),
        ], $actor->id);

        return $csv;
    }

    private function ensureWritableVersion(int $tenantId, FormVersion $version): void
    {
        $form = $this->formRepository->findInTenant($tenantId, $version->form_id, true);
        if (! $form || ! $form->is_active || ! $version->is_published) {
            throw new BusinessException('Este formulário não está mais disponível', 'form_unavailable');
        }
    }
}

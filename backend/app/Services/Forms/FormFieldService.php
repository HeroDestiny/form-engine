<?php

namespace App\Services\Forms;

use App\Models\FormField;
use App\Models\FormVersion;
use App\Repositories\Contracts\FormFieldRepositoryInterface;
use App\Repositories\Contracts\FormVersionRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class FormFieldService
{
    public function __construct(
        private readonly FormFieldRepositoryInterface $formFieldRepository,
        private readonly FormVersionRepositoryInterface $formVersionRepository,
    ) {}

    public function listForVersionInTenant(int $tenantId, int $formId, int $versionId): Collection
    {
        return $this->formFieldRepository->listForVersionInTenant($tenantId, $formId, $versionId);
    }

    public function findVersionInTenant(int $tenantId, int $formId, int $versionId): ?FormVersion
    {
        return $this->formVersionRepository->findInTenant($tenantId, $formId, $versionId);
    }

    public function findFieldInTenant(int $tenantId, int $formId, int $versionId, int $fieldId): ?FormField
    {
        return $this->formFieldRepository->findInTenant($tenantId, $formId, $versionId, $fieldId);
    }

    public function storeRules(int $versionId): array
    {
        return [
            'label' => ['required', 'string', 'min:2', 'max:255'],
            'name' => [
                'required',
                'string',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('form_fields', 'name')->where(static fn ($query) => $query->where('form_version_id', $versionId)),
            ],
            'type' => ['required', Rule::in(['text', 'textarea', 'number', 'email', 'date', 'select', 'radio', 'checkbox'])],
            'is_required' => ['required', 'boolean'],
            'order' => ['required', 'integer', 'min:1'],
            'options' => ['required_if:type,select,radio,checkbox', 'array'],
            'options.*.value' => ['required_with:options', 'string'],
            'options.*.label' => ['required_with:options', 'string'],
        ];
    }

    public function updateRules(int $versionId, int $fieldId): array
    {
        return [
            'label' => ['required', 'string', 'min:2', 'max:255'],
            'name' => [
                'required',
                'string',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('form_fields', 'name')
                    ->where(static fn ($query) => $query->where('form_version_id', $versionId))
                    ->ignore($fieldId),
            ],
            'type' => ['required', Rule::in(['text', 'textarea', 'number', 'email', 'date', 'select', 'radio', 'checkbox'])],
            'is_required' => ['required', 'boolean'],
            'order' => ['required', 'integer', 'min:1'],
            'options' => ['required_if:type,select,radio,checkbox', 'array'],
            'options.*.value' => ['required_with:options', 'string'],
            'options.*.label' => ['required_with:options', 'string'],
        ];
    }

    public function create(FormVersion $version, array $payload): FormField
    {
        return $this->formFieldRepository->create([
            'form_version_id' => $version->id,
            'label' => $payload['label'],
            'name' => $payload['name'],
            'type' => $payload['type'],
            'is_required' => $payload['is_required'],
            'options' => $payload['options'],
            'order' => $payload['order'],
        ]);
    }

    public function update(FormField $field, array $payload): FormField
    {
        return $this->formFieldRepository->update($field, [
            'label' => $payload['label'],
            'name' => $payload['name'],
            'type' => $payload['type'],
            'is_required' => $payload['is_required'],
            'options' => $payload['options'],
            'order' => $payload['order'],
        ]);
    }

    public function delete(FormField $field): void
    {
        $this->formFieldRepository->delete($field);
    }
}

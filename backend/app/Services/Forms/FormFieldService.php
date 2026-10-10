<?php

namespace App\Services\Forms;

use App\Exceptions\BusinessException;
use App\Exceptions\NotFoundException;
use App\Models\FormField;
use App\Models\FormVersion;
use App\Repositories\Contracts\FormFieldRepositoryInterface;
use App\Repositories\Contracts\FormVersionRepositoryInterface;
use Illuminate\Support\Collection;

class FormFieldService
{
    public function __construct(
        private readonly FormFieldRepositoryInterface $formFieldRepository,
        private readonly FormVersionRepositoryInterface $formVersionRepository,
    ) {}

    public function listForVersionInTenant(int $tenantId, int $formId, int $versionId): Collection
    {
        $this->requireVersion($tenantId, $formId, $versionId);

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

    public function create(FormVersion $version, array $payload): FormField
    {
        $this->ensureEditable($version);

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
        $this->ensureEditable($field->version);

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
        if ($field->version->is_published) {
            throw new BusinessException('Campos de versões publicadas não podem ser removidos', 'field_removal_forbidden');
        }
        $this->formFieldRepository->delete($field);
    }

    public function requireVersion(int $tenantId, int $formId, int $versionId): FormVersion
    {
        return $this->formVersionRepository->findInTenant($tenantId, $formId, $versionId) ?? throw new NotFoundException;
    }

    public function requireField(int $tenantId, int $formId, int $versionId, int $fieldId): FormField
    {
        return $this->formFieldRepository->findInTenant($tenantId, $formId, $versionId, $fieldId) ?? throw new NotFoundException('Campo não encontrado');
    }

    public function ensureEditable(FormVersion $version): void
    {
        if ($version->is_published) {
            throw new BusinessException('Versão não pode ser editada');
        }
    }
}

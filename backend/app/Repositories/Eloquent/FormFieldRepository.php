<?php

namespace App\Repositories\Eloquent;

use App\Models\FormField;
use App\Repositories\Contracts\FormFieldRepositoryInterface;
use Illuminate\Support\Collection;

class FormFieldRepository implements FormFieldRepositoryInterface
{
    public function listForVersionInTenant(int $tenantId, int $formId, int $versionId): Collection
    {
        return FormField::query()
            ->where('form_version_id', $versionId)
            ->whereHas('version', function ($query) use ($tenantId, $formId, $versionId) {
                $query->where('id', $versionId)
                    ->where('form_id', $formId)
                    ->whereHas('form', fn ($nested) => $nested->where('tenant_id', $tenantId));
            })
            ->orderBy('order')
            ->get();
    }

    public function findInTenant(int $tenantId, int $formId, int $versionId, int $fieldId): ?FormField
    {
        return FormField::query()
            ->where('id', $fieldId)
            ->where('form_version_id', $versionId)
            ->whereHas('version', function ($query) use ($tenantId, $formId, $versionId) {
                $query->where('id', $versionId)
                    ->where('form_id', $formId)
                    ->whereHas('form', fn ($nested) => $nested->where('tenant_id', $tenantId));
            })
            ->first();
    }

    public function listForVersion(int $versionId): Collection
    {
        return FormField::query()->where('form_version_id', $versionId)->get();
    }

    public function create(array $data): FormField
    {
        return FormField::query()->create($data);
    }

    public function update(FormField $field, array $data): FormField
    {
        $field->update($data);

        return $field->fresh();
    }

    public function delete(FormField $field): void
    {
        $field->delete();
    }
}

<?php

namespace App\Repositories\Eloquent;

use App\Models\FormVersion;
use App\Repositories\Contracts\FormVersionRepositoryInterface;
use Illuminate\Support\Collection;

class FormVersionRepository implements FormVersionRepositoryInterface
{
    public function listForFormInTenant(int $tenantId, int $formId): Collection
    {
        return FormVersion::query()
            ->where('form_id', $formId)
            ->whereHas('form', fn ($query) => $query->where('tenant_id', $tenantId))
            ->with(['fields' => fn ($query) => $query->orderBy('order')])
            ->orderByDesc('version_number')
            ->get();
    }

    public function create(array $data): FormVersion
    {
        return FormVersion::query()->create($data);
    }

    public function findInTenant(int $tenantId, int $formId, int $versionId): ?FormVersion
    {
        return FormVersion::query()
            ->where('id', $versionId)
            ->where('form_id', $formId)
            ->whereHas('form', fn ($query) => $query->where('tenant_id', $tenantId))
            ->first();
    }

    public function listPublishedByForm(int $formId): Collection
    {
        return FormVersion::query()
            ->where('form_id', $formId)
            ->where('is_published', true)
            ->orderByDesc('version_number')
            ->get();
    }

    public function findLatestPublishedByForm(int $formId): ?FormVersion
    {
        return FormVersion::query()
            ->where('form_id', $formId)
            ->where('is_published', true)
            ->orderByDesc('version_number')
            ->with('fields')
            ->first();
    }
}

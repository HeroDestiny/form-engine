<?php

namespace App\Repositories\Contracts;

use App\Models\FormVersion;
use Illuminate\Support\Collection;

interface FormVersionRepositoryInterface
{
    public function listForFormInTenant(int $tenantId, int $formId): Collection;

    public function create(array $data): FormVersion;

    public function findInTenant(int $tenantId, int $formId, int $versionId): ?FormVersion;

    public function listPublishedByForm(int $formId): Collection;

    public function findLatestPublishedByForm(int $formId): ?FormVersion;
}

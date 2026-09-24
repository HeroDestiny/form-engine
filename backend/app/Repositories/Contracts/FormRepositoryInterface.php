<?php

namespace App\Repositories\Contracts;

use App\Models\Form;
use Illuminate\Support\Collection;

interface FormRepositoryInterface
{
    public function listAvailableForTenant(int $tenantId, ?string $search, string $sort, string $order): Collection;

    public function listAllForTenant(int $tenantId, ?string $search, string $sort, string $order): Collection;

    public function findDetailedInTenant(int $tenantId, int $formId): ?Form;

    public function create(array $data): Form;

    public function findInTenant(int $tenantId, int $formId): ?Form;

    public function updateStatus(Form $form, bool $isActive): Form;
}

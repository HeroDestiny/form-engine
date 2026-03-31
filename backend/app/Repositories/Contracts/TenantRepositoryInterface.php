<?php

namespace App\Repositories\Contracts;

use App\Models\Tenant;
use Illuminate\Pagination\LengthAwarePaginator;

interface TenantRepositoryInterface
{
    public function paginateAll(int $perPage): LengthAwarePaginator;

    public function create(array $data): Tenant;

    public function find(int $tenantId): ?Tenant;

    public function updateStatus(Tenant $tenant, bool $isActive): Tenant;
}

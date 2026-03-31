<?php

namespace App\Repositories\Eloquent;

use App\Models\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class TenantRepository implements TenantRepositoryInterface
{
    public function paginateAll(int $perPage): LengthAwarePaginator
    {
        return Tenant::query()->orderBy('id')->paginate($perPage);
    }

    public function create(array $data): Tenant
    {
        return Tenant::query()->create($data);
    }

    public function find(int $tenantId): ?Tenant
    {
        return Tenant::query()->find($tenantId);
    }

    public function updateStatus(Tenant $tenant, bool $isActive): Tenant
    {
        $tenant->update(['is_active' => $isActive]);

        return $tenant->fresh();
    }
}

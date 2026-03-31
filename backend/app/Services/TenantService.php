<?php

namespace App\Services;

use App\Models\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class TenantService
{
    public function __construct(private readonly TenantRepositoryInterface $tenantRepository) {}

    public function listPaginated(int $perPage = 20): LengthAwarePaginator
    {
        return $this->tenantRepository->paginateAll($perPage);
    }

    public function create(string $name, string $slug): Tenant
    {
        return $this->tenantRepository->create([
            'name' => $name,
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    public function updateStatus(Tenant $tenant, bool $isActive): Tenant
    {
        return $this->tenantRepository->updateStatus($tenant, $isActive);
    }
}

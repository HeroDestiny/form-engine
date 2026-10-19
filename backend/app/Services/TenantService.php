<?php

namespace App\Services;

use App\Models\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TenantService
{
    public function __construct(private readonly TenantRepositoryInterface $tenantRepository, private readonly AuditRecorder $audit) {}

    public function listPaginated(int $perPage = 20): LengthAwarePaginator
    {
        return $this->tenantRepository->paginateAll($perPage);
    }

    public function create(string $name, string $slug): Tenant
    {
        return DB::transaction(function () use ($name, $slug) {
            $tenant = $this->tenantRepository->create([
                'name' => $name,
                'slug' => $slug,
                'is_active' => true,
            ]);
            $this->audit->record('CREATE_TENANT', $tenant->id, 'tenant', $tenant->id);

            return $tenant;
        });
    }

    public function updateStatus(Tenant $tenant, bool $isActive): Tenant
    {
        return DB::transaction(function () use ($tenant, $isActive) {
            $updated = $this->tenantRepository->updateStatus($tenant, $isActive);
            $this->audit->record('UPDATE_TENANT', $tenant->id, 'tenant', $tenant->id, ['is_active' => $isActive]);

            return $updated;
        });
    }

    public function find(int $tenantId): ?Tenant
    {
        return $this->tenantRepository->find($tenantId);
    }
}

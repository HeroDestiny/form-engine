<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AuditLogService
{
    public function __construct(private readonly AuditLogRepositoryInterface $auditLogRepository) {}

    public function listForTenantPaginated(int $tenantId, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->auditLogRepository->paginateForTenant($tenantId, $filters, $perPage);
    }

    public function listForTenant(int $tenantId, ?string $action): Collection
    {
        return $this->auditLogRepository->listForTenant($tenantId, $action);
    }

    public function findInTenant(int $tenantId, int $logId): ?AuditLog
    {
        return $this->auditLogRepository->findInTenant($tenantId, $logId);
    }
}

<?php

namespace App\Repositories\Contracts;

use App\Models\AuditLog;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface AuditLogRepositoryInterface
{
    public function paginateForTenant(int $tenantId, array $filters, int $perPage): LengthAwarePaginator;

    public function listForTenant(int $tenantId, ?string $action): Collection;

    public function findInTenant(int $tenantId, int $logId): ?AuditLog;
}

<?php

namespace App\Repositories\Eloquent;

use App\Models\AuditLog;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AuditLogRepository implements AuditLogRepositoryInterface
{
    public function paginateForTenant(int $tenantId, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = AuditLog::query()
            ->with('user')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at');

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }
        if (! empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }
        if (! empty($filters['entity_id'])) {
            $query->where('entity_id', (int) $filters['entity_id']);
        }
        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query->paginate($perPage);
    }

    public function listForTenant(int $tenantId, ?string $action): Collection
    {
        $query = AuditLog::query()
            ->with('user')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at');

        if ($action !== null && $action !== '') {
            $query->where('action', $action);
        }

        return $query->get();
    }

    public function findInTenant(int $tenantId, int $logId): ?AuditLog
    {
        return AuditLog::query()
            ->with('user')
            ->where('tenant_id', $tenantId)
            ->where('id', $logId)
            ->first();
    }
}

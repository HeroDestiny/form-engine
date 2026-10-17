<?php

namespace App\Repositories\Eloquent;

use App\Models\AuditLog;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\FormVersion;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AuditLogRepository implements AuditLogRepositoryInterface
{
    public function paginateForTenant(int $tenantId, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = AuditLog::query()
            ->with('user')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')->orderByDesc('id');

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
            $query->where('created_at', '>=', CarbonImmutable::parse($filters['date_from'], 'UTC')->utc()->toDateTimeString());
        }
        if (! empty($filters['date_to'])) {
            $end = CarbonImmutable::parse($filters['date_to'], 'UTC')->utc();
            if (strlen($filters['date_to']) === 10) {
                $end = $end->endOfDay();
            }
            $query->where('created_at', '<=', $end->toDateTimeString());
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

    public function entityExists(AuditLog $log): bool
    {
        if (! $log->entity_type || ! $log->entity_id) {
            return false;
        }

        $modelClass = $this->resolveEntityModelClass($log->entity_type);
        if (! $modelClass) {
            return false;
        }

        $query = $modelClass::query()->whereKey($log->entity_id);

        return match ($log->entity_type) {
            'tenant' => $query->whereKey($log->tenant_id)->exists(),
            'form_field' => $query->whereHas('version.form', fn ($form) => $form->where('tenant_id', $log->tenant_id))->exists(),
            'form_version' => $query->whereHas('form', fn ($form) => $form->where('tenant_id', $log->tenant_id))->exists(),
            default => $query->where('tenant_id', $log->tenant_id)->exists(),
        };
    }

    public function relatedActionsCount(AuditLog $log): int
    {
        if (! $log->entity_type || ! $log->entity_id || ! $log->tenant_id) {
            return 0;
        }

        return AuditLog::query()
            ->where('tenant_id', $log->tenant_id)
            ->where('entity_type', $log->entity_type)
            ->where('entity_id', $log->entity_id)
            ->where('id', '!=', $log->id)
            ->count();
    }

    private function resolveEntityModelClass(string $entityType): ?string
    {
        return match ($entityType) {
            'tenant' => Tenant::class,
            'user' => User::class,
            'form' => Form::class,
            'form_version' => FormVersion::class,
            'form_field' => FormField::class,
            'form_submission' => FormSubmission::class,
            default => null,
        };
    }
}

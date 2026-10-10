<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditRecorder
{
    public function record(string $action, ?int $tenantId, string $entityType, ?int $entityId, array $metadata = [], ?int $actorId = null): AuditLog
    {
        $request = app()->bound('request') ? app('request') : null;

        return AuditLog::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $actorId ?? $request?->user()?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListAuditLogsRequest;
use App\Models\AuditLog;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function index(ListAuditLogsRequest $request): JsonResponse
    {
        $paginator = $this->auditLogService->listForTenantPaginated(
            (int) $request->user()?->tenant_id,
            $request->validated(),
            $request->perPage(),
        );

        $logs = collect($paginator->items())->map(function (AuditLog $log) {
            return [
                'id' => $log->id,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ] : null,
                'action' => $log->action,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'metadata' => $log->metadata,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'logs' => $logs,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
            'message' => null,
            'errors' => null,
        ]);
    }

    public function show(Request $request, int $logId): JsonResponse
    {
        $log = $this->auditLogService->findInTenant((int) $request->user()?->tenant_id, $logId);

        if (! $log) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Registro não encontrado',
                'errors' => null,
            ], 404);
        }

        $entityExists = $this->auditLogService->entityExists($log);
        $relatedActionsCount = $this->auditLogService->relatedActionsCount($log);

        return response()->json([
            'success' => true,
            'data' => [
                'log' => [
                    'id' => $log->id,
                    'tenant_id' => $log->tenant_id,
                    'action' => $log->action,
                    'entity_type' => $log->entity_type,
                    'entity_id' => $log->entity_id,
                    'metadata' => $log->metadata,
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                    'created_at' => $log->created_at,
                ],
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                    'role' => $log->user->role,
                    'is_active' => $log->user->is_active,
                ] : null,
                'entity_exists' => $entityExists,
                'related_actions_count' => $relatedActionsCount,
            ],
            'message' => null,
            'errors' => null,
        ]);
    }
}

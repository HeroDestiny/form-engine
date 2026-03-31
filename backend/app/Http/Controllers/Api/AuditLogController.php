<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\FormVersion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->integer('per_page', 20)));

        $paginator = $this->auditLogService->listForTenantPaginated(
            (int) $request->user()?->tenant_id,
            [
                'action' => $request->filled('action') ? $request->string('action')->toString() : null,
                'user_id' => $request->filled('user_id') ? (int) $request->integer('user_id') : null,
                'entity_type' => $request->filled('entity_type') ? $request->string('entity_type')->toString() : null,
                'entity_id' => $request->filled('entity_id') ? (int) $request->integer('entity_id') : null,
                'date_from' => $request->filled('date_from') ? $request->string('date_from')->toString() : null,
                'date_to' => $request->filled('date_to') ? $request->string('date_to')->toString() : null,
            ],
            $perPage,
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

        $entityExists = $this->entityExists($log);
        $relatedActionsCount = $this->relatedActionsCount($log);

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

    private function entityExists(AuditLog $log): bool
    {
        if (! $log->entity_type || ! $log->entity_id) {
            return false;
        }

        $modelClass = $this->resolveEntityModelClass($log->entity_type);
        if (! $modelClass) {
            return false;
        }

        return $modelClass::query()->where('id', $log->entity_id)->exists();
    }

    private function relatedActionsCount(AuditLog $log): int
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
            'form_submission' => FormSubmission::class,
            default => null,
        };
    }
}

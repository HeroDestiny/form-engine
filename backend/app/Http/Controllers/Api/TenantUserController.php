<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginatedQueryRequest;
use App\Http\Requests\StoreTenantUserRequest;
use App\Http\Requests\UpdateStatusRequest;
use App\Services\TenantUserService;
use Illuminate\Http\JsonResponse;

class TenantUserController extends Controller
{
    public function __construct(private readonly TenantUserService $tenantUserService) {}

    public function index(PaginatedQueryRequest $request, int $tenantId): JsonResponse
    {
        $paginator = $this->tenantUserService->listForTenantPaginated($tenantId, $request->perPage());

        return response()->json([
            'success' => true,
            'data' => [
                'users' => $paginator->items(),
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

    public function store(StoreTenantUserRequest $request, int $tenantId): JsonResponse
    {
        $user = $this->tenantUserService->create(
            $tenantId,
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->string('role')->toString(),
        );

        return response()->json([
            'success' => true,
            'data' => $this->tenantUserService->toApiPayload($user),
            'message' => 'Usuário criado com sucesso',
            'errors' => null,
        ], 201);
    }

    public function updateStatus(UpdateStatusRequest $request, int $tenantId, int $userId): JsonResponse
    {
        $targetUser = $this->tenantUserService->changeStatus($tenantId, $userId, (int) $request->user()->id, $request->boolean('is_active'));

        return response()->json([
            'success' => true,
            'data' => $this->tenantUserService->toApiPayload($targetUser),
            'message' => $targetUser->is_active ? 'Usuário ativado com sucesso' : 'Usuário desativado com sucesso',
            'errors' => null,
        ]);
    }
}

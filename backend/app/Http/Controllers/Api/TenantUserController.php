<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TenantUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TenantUserController extends Controller
{
    public function __construct(private readonly TenantUserService $tenantUserService) {}

    public function index(int $tenantId): JsonResponse
    {
        $perPage = max(1, min(100, (int) request()->integer('per_page', 20)));
        $paginator = $this->tenantUserService->listForTenantPaginated($tenantId, $perPage);

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

    public function store(Request $request, int $tenantId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->where(static fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['admin', 'manager', 'user'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Erro de validação',
                'errors' => $validator->errors(),
            ], 422);
        }

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

    public function updateStatus(Request $request, int $tenantId, int $userId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'is_active' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Erro de validação',
                'errors' => $validator->errors(),
            ], 422);
        }

        $authUser = $request->user();

        if ((int) $authUser?->id === $userId && ! $request->boolean('is_active')) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Você não pode desativar sua própria conta',
                'errors' => null,
            ], 403);
        }

        $targetUser = $this->tenantUserService->findInTenant($tenantId, $userId);

        if (! $targetUser) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Usuário não encontrado',
                'errors' => null,
            ], 404);
        }

        $targetUser = $this->tenantUserService->updateStatus($targetUser, (bool) $request->boolean('is_active'));

        return response()->json([
            'success' => true,
            'data' => $this->tenantUserService->toApiPayload($targetUser),
            'message' => $targetUser->is_active ? 'Usuário ativado com sucesso' : 'Usuário desativado com sucesso',
            'errors' => null,
        ]);
    }
}

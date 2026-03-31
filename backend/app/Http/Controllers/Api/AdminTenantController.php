<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminTenantController extends Controller
{
    public function __construct(private readonly TenantService $tenantService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->integer('per_page', 20)));
        $paginator = $this->tenantService->listPaginated($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'tenants' => $paginator->items(),
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

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'slug' => ['required', 'string', 'regex:/^[a-z0-9-]+$/', 'unique:tenants,slug'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Erro de validação',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenant = $this->tenantService->create(
            $request->string('name')->toString(),
            $request->string('slug')->toString(),
        );

        return response()->json([
            'success' => true,
            'data' => $tenant,
            'message' => 'Tenant criado com sucesso',
            'errors' => null,
        ], 201);
    }

    public function updateStatus(Request $request, int $tenantId): JsonResponse
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

        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Tenant não encontrado',
                'errors' => null,
            ], 404);
        }

        $tenant = $this->tenantService->updateStatus($tenant, (bool) $request->boolean('is_active'));

        return response()->json([
            'success' => true,
            'data' => $tenant,
            'message' => $tenant->is_active ? 'Tenant ativado com sucesso' : 'Tenant desativado com sucesso',
            'errors' => null,
        ]);
    }
}

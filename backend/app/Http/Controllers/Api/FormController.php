<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListFormsRequest;
use App\Http\Requests\StoreFormRequest;
use App\Http\Requests\UpdateStatusRequest;
use App\Services\Forms\FormService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormController extends Controller
{
    public function __construct(private readonly FormService $formService) {}

    public function index(ListFormsRequest $request): JsonResponse
    {
        $search = $request->validated('search');
        $sort = $request->validated('sort') ?: 'name';
        $order = $request->validated('order') ?: 'asc';
        $scope = $request->validated('scope') ?: '';
        $forms = $this->formService->listForUser($request->user(), $search, $sort, $order, $scope);

        return response()->json([
            'success' => true,
            'data' => [
                'forms' => $forms,
                'total' => $forms->count(),
            ],
            'message' => $forms->isEmpty() ? 'Nenhum formulário disponível no momento' : null,
            'errors' => null,
        ]);
    }

    public function show(Request $request, int $formId): JsonResponse
    {
        $form = $this->formService->findDetailedInTenant((int) $request->user()?->tenant_id, $formId);

        if (! $form) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Recurso não encontrado',
                'errors' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'form' => $form,
            ],
            'message' => null,
            'errors' => null,
        ]);
    }

    public function storeForCurrentTenant(StoreFormRequest $request): JsonResponse
    {
        return $this->store($request, (int) $request->user()?->tenant_id);
    }

    public function store(StoreFormRequest $request, int $tenantId): JsonResponse
    {
        $payload = $this->formService->createWithInitialVersion(
            $tenantId,
            $request->string('name')->toString(),
            $request->filled('description') ? $request->string('description')->toString() : null,
            (int) $request->user()?->id,
        );

        return response()->json([
            'success' => true,
            'data' => $payload,
            'message' => 'Formulário criado com sucesso. Adicione campos para publicar.',
            'errors' => null,
        ], 201);
    }

    public function updateStatus(UpdateStatusRequest $request, int $formId): JsonResponse
    {
        $form = $this->formService->findInTenant((int) $request->user()?->tenant_id, $formId);

        if (! $form) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Formulário não encontrado',
                'errors' => null,
            ], 404);
        }

        $form = $this->formService->updateStatus($form, (bool) $request->boolean('is_active'));

        return response()->json([
            'success' => true,
            'data' => $form,
            'message' => $form->is_active ? 'Formulário ativado com sucesso' : 'Formulário desativado com sucesso',
            'errors' => null,
        ]);
    }
}

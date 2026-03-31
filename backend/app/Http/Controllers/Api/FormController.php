<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Forms\FormService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FormController extends Controller
{
    public function __construct(private readonly FormService $formService) {}

    public function index(Request $request): JsonResponse
    {
        $forms = $this->formService->listAvailableForTenant(
            (int) $request->user()?->tenant_id,
            $request->filled('search') ? $request->string('search')->toString() : null,
            $request->string('sort')->toString() ?: 'name',
            $request->string('order')->toString() ?: 'asc',
        );

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

    public function storeForCurrentTenant(Request $request): JsonResponse
    {
        return $this->store($request, (int) $request->user()?->tenant_id);
    }

    public function store(Request $request, int $tenantId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'min:3',
                'max:255',
                Rule::unique('forms', 'name')->where(static fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Erro de validação',
                'errors' => $validator->errors(),
            ], 422);
        }

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

    public function updateStatus(Request $request, int $formId): JsonResponse
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

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Forms\FormFieldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FormFieldController extends Controller
{
    public function __construct(private readonly FormFieldService $formFieldService) {}

    public function index(Request $request, int $formId, int $versionId): JsonResponse
    {
        $fields = $this->formFieldService->listForVersionInTenant((int) $request->user()?->tenant_id, $formId, $versionId);

        if ($fields->isEmpty()) {
            $version = $this->formFieldService->findVersionInTenant((int) $request->user()?->tenant_id, $formId, $versionId);

            if (! $version) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Recurso não encontrado',
                    'errors' => null,
                ], 404);
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'fields' => $fields,
            ],
            'message' => null,
            'errors' => null,
        ]);
    }

    public function store(Request $request, int $formId, int $versionId): JsonResponse
    {
        $version = $this->formFieldService->findVersionInTenant((int) $request->user()?->tenant_id, $formId, $versionId);

        if (! $version) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Recurso não encontrado',
                'errors' => null,
            ], 404);
        }

        if ($version->is_published) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Versão não pode ser editada',
                'errors' => null,
            ], 422);
        }

        $validator = Validator::make($request->all(), $this->formFieldService->storeRules($versionId));

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Erro de validação',
                'errors' => $validator->errors(),
            ], 422);
        }

        $field = $this->formFieldService->create($version, [
            'label' => $request->string('label')->toString(),
            'name' => $request->string('name')->toString(),
            'type' => $request->string('type')->toString(),
            'is_required' => (bool) $request->boolean('is_required'),
            'options' => $request->input('options'),
            'order' => (int) $request->integer('order'),
        ]);

        return response()->json([
            'success' => true,
            'data' => $field,
            'message' => 'Campo adicionado com sucesso',
            'errors' => null,
        ], 201);
    }

    public function update(Request $request, int $formId, int $versionId, int $fieldId): JsonResponse
    {
        $field = $this->formFieldService->findFieldInTenant((int) $request->user()?->tenant_id, $formId, $versionId, $fieldId);

        if (! $field) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Campo não encontrado',
                'errors' => null,
            ], 404);
        }

        $version = $field->version;
        if ($version->is_published) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Versão não pode ser editada',
                'errors' => null,
            ], 422);
        }

        $validator = Validator::make($request->all(), $this->formFieldService->updateRules($versionId, $field->id));

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Erro de validação',
                'errors' => $validator->errors(),
            ], 422);
        }

        $field = $this->formFieldService->update($field, [
            'label' => $request->string('label')->toString(),
            'name' => $request->string('name')->toString(),
            'type' => $request->string('type')->toString(),
            'is_required' => (bool) $request->boolean('is_required'),
            'options' => $request->input('options'),
            'order' => (int) $request->integer('order'),
        ]);

        return response()->json([
            'success' => true,
            'data' => $field,
            'message' => 'Campo atualizado com sucesso',
            'errors' => null,
        ]);
    }

    public function destroy(Request $request, int $formId, int $versionId, int $fieldId): JsonResponse
    {
        $field = $this->formFieldService->findFieldInTenant((int) $request->user()?->tenant_id, $formId, $versionId, $fieldId);

        if (! $field) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Campo não encontrado',
                'errors' => null,
            ], 404);
        }

        if ($field->version->is_published) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Campos de versões publicadas não podem ser removidos',
                'errors' => null,
            ], 400);
        }

        $this->formFieldService->delete($field);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Campo removido com sucesso',
            'errors' => null,
        ]);
    }
}

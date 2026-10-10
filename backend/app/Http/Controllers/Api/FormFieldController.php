<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FormFieldRequest;
use App\Services\Forms\FormFieldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormFieldController extends Controller
{
    public function __construct(private readonly FormFieldService $formFieldService) {}

    public function index(Request $request, int $formId, int $versionId): JsonResponse
    {
        $fields = $this->formFieldService->listForVersionInTenant((int) $request->user()->tenant_id, $formId, $versionId);

        return response()->json(['success' => true, 'data' => ['fields' => $fields], 'message' => null, 'errors' => null]);
    }

    public function store(FormFieldRequest $request, int $formId, int $versionId): JsonResponse
    {
        $field = $this->formFieldService->create($request->formVersion(), $request->fieldPayload());

        return response()->json(['success' => true, 'data' => $field, 'message' => 'Campo adicionado com sucesso', 'errors' => null], 201);
    }

    public function update(FormFieldRequest $request, int $formId, int $versionId, int $fieldId): JsonResponse
    {
        $field = $this->formFieldService->update($request->formField(), $request->fieldPayload());

        return response()->json(['success' => true, 'data' => $field, 'message' => 'Campo atualizado com sucesso', 'errors' => null]);
    }

    public function destroy(Request $request, int $formId, int $versionId, int $fieldId): JsonResponse
    {
        $field = $this->formFieldService->requireField((int) $request->user()->tenant_id, $formId, $versionId, $fieldId);
        $this->formFieldService->delete($field);

        return response()->json(['success' => true, 'data' => null, 'message' => 'Campo removido com sucesso', 'errors' => null]);
    }
}

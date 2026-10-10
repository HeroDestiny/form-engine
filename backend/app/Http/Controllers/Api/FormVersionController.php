<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Forms\FormVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormVersionController extends Controller
{
    public function __construct(private readonly FormVersionService $formVersionService) {}

    public function index(Request $request, int $formId): JsonResponse
    {
        $versions = $this->formVersionService->listForFormInTenant((int) $request->user()->tenant_id, $formId);

        return response()->json(['success' => true, 'data' => ['versions' => $versions], 'message' => null, 'errors' => null]);
    }

    public function store(Request $request, int $formId): JsonResponse
    {
        $payload = $this->formVersionService->createNextVersion((int) $request->user()->tenant_id, $formId, (int) $request->user()->id);

        return response()->json([
            'success' => true,
            'data' => $payload,
            'message' => 'Nova versão criada com sucesso. '.$payload['fields_copied'].' campos copiados da versão '.$payload['from_version'].'.',
            'errors' => null,
        ], 201);
    }

    public function publish(Request $request, int $formId, int $versionId): JsonResponse
    {
        $version = $this->formVersionService->publish((int) $request->user()->tenant_id, $formId, $versionId);

        return response()->json([
            'success' => true,
            'data' => $version,
            'message' => 'Versão publicada com sucesso. Formulário disponível para preenchimento.',
            'errors' => null,
        ]);
    }
}

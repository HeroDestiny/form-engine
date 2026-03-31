<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormVersion;
use App\Services\Forms\FormVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormVersionController extends Controller
{
    public function __construct(private readonly FormVersionService $formVersionService) {}

    public function index(Request $request, int $formId): JsonResponse
    {
        $versions = $this->formVersionService->listForFormInTenant((int) $request->user()?->tenant_id, $formId);

        if ($versions->isEmpty()) {
            $formExists = Form::query()
                ->where('id', $formId)
                ->where('tenant_id', $request->user()?->tenant_id)
                ->exists();

            if (! $formExists) {
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
                'versions' => $versions,
            ],
            'message' => null,
            'errors' => null,
        ]);
    }

    public function store(Request $request, int $formId): JsonResponse
    {
        $form = Form::query()
            ->where('id', $formId)
            ->where('tenant_id', $request->user()?->tenant_id)
            ->with(['versions' => fn ($query) => $query->orderByDesc('version_number')])
            ->first();

        if (! $form) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Recurso não encontrado',
                'errors' => null,
            ], 404);
        }

        $draft = $form->versions->first(fn (FormVersion $version) => ! $version->is_published);
        if ($draft) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Já existe uma versão em edição',
                'errors' => null,
            ], 400);
        }

        $published = $form->versions->first(fn (FormVersion $version) => $version->is_published);
        if (! $published) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Publique a versão atual antes de criar nova versão',
                'errors' => null,
            ], 400);
        }

        $payload = $this->formVersionService->createNextVersionFromPublished(
            $form,
            $published,
            (int) $request->user()?->id,
        );

        return response()->json([
            'success' => true,
            'data' => $payload,
            'message' => 'Nova versão criada com sucesso. '.$payload['fields_copied'].' campos copiados da versão '.$payload['from_version'].'.',
            'errors' => null,
        ], 201);
    }

    public function publish(Request $request, int $formId, int $versionId): JsonResponse
    {
        $version = FormVersion::query()
            ->where('id', $versionId)
            ->where('form_id', $formId)
            ->whereHas('form', fn ($query) => $query->where('tenant_id', $request->user()?->tenant_id))
            ->with('fields')
            ->first();

        if (! $version) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Recurso não encontrado',
                'errors' => null,
            ], 404);
        }

        if ($version->is_published || $version->fields->isEmpty()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Versão não pode ser publicada',
                'errors' => null,
            ], 422);
        }

        $version->update([
            'is_published' => true,
            'published_at' => now(),
        ]);

        $publishedVersion = FormVersion::query()
            ->where('id', $version->id)
            ->with(['fields' => fn ($query) => $query->orderBy('order')])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $publishedVersion,
            'message' => 'Versão publicada com sucesso. Formulário disponível para preenchimento.',
            'errors' => null,
        ]);
    }
}

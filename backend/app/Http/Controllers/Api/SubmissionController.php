<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExportSubmissionsRequest;
use App\Http\Requests\FinalizeDraftRequest;
use App\Http\Requests\ListSubmissionsRequest;
use App\Http\Requests\SaveDraftRequest;
use App\Http\Requests\SubmitFormRequest;
use App\Http\Requests\UpdateDraftRequest;
use App\Services\Forms\SubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionController extends Controller
{
    public function __construct(private readonly SubmissionService $submissionService) {}

    public function index(ListSubmissionsRequest $request): JsonResponse
    {
        $paginator = $this->submissionService->listForUser($request->user(), $request->validated(), $request->perPage());
        $submissions = $paginator->getCollection();

        return response()->json([
            'success' => true,
            'data' => [
                'submissions' => $submissions,
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

    public function listDrafts(Request $request, int $formId): JsonResponse
    {
        $drafts = $this->submissionService->listDrafts($request->user(), $formId);

        return response()->json([
            'success' => true,
            'data' => [
                'drafts' => $drafts,
            ],
            'message' => null,
            'errors' => null,
        ]);
    }

    public function show(Request $request, int $submissionId): JsonResponse
    {
        $payload = $this->submissionService->details($request->user(), $submissionId);

        return response()->json(['success' => true, 'data' => $payload, 'message' => null, 'errors' => null]);
    }

    public function export(ExportSubmissionsRequest $request): StreamedResponse
    {
        $csv = $this->submissionService->exportForUser($request->user(), $request->validated());

        return response()->streamDownload(function () use ($csv) {
            try {
                fpassthru($csv['stream']);
            } finally {
                fclose($csv['stream']);
            }
        }, $csv['filename'], ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Length' => (string) $csv['bytes']]);
    }

    public function submit(SubmitFormRequest $request, int $formId): JsonResponse
    {
        ['form' => $form, 'version' => $version] = $request->submissionContext();

        $submission = $this->submissionService->createSubmitted(
            (int) $request->user()?->tenant_id,
            (int) $request->user()?->id,
            $version,
            $request->values(),
        );

        return response()->json([
            'success' => true,
            'data' => [
                'submission' => $submission,
                'form' => [
                    'id' => $form->id,
                    'name' => $form->name,
                    'version' => $version->version_number,
                ],
            ],
            'message' => 'Formulário enviado com sucesso!',
            'errors' => null,
        ], 201);
    }

    public function saveDraft(SaveDraftRequest $request, int $formId): JsonResponse
    {
        ['form' => $form, 'version' => $version] = $request->submissionContext();

        $draft = $this->submissionService->createDraft(
            (int) $request->user()?->tenant_id,
            (int) $request->user()?->id,
            $version,
            $request->values(),
        );

        return response()->json([
            'success' => true,
            'data' => [
                'draft' => $draft,
                'form' => [
                    'id' => $form->id,
                    'name' => $form->name,
                    'version' => $version?->version_number,
                ],
                'fields_filled' => count($request->values()),
                'fields_total' => $version?->fields->count() ?? 0,
            ],
            'message' => 'Rascunho salvo com sucesso. Você pode continuar depois.',
            'errors' => null,
        ], 201);
    }

    public function updateDraft(UpdateDraftRequest $request, int $formId, int $submissionId): JsonResponse
    {
        $draft = $this->submissionService->saveExistingDraft($request->user(), $formId, $submissionId, $request->values());

        return response()->json(['success' => true, 'data' => ['draft' => $draft], 'message' => 'Rascunho atualizado com sucesso', 'errors' => null]);
    }

    public function finalizeDraft(FinalizeDraftRequest $request, int $formId, int $submissionId): JsonResponse
    {
        $submission = $this->submissionService->saveExistingDraft($request->user(), $formId, $submissionId, $request->values(), true);

        return response()->json(['success' => true, 'data' => ['submission' => $submission], 'message' => 'Formulário enviado com sucesso!', 'errors' => null]);
    }

    public function uploadAttachment(Request $request, int $formId): JsonResponse
    {
        $tenantId = (int) $request->user()->tenant_id;
        $context = $this->submissionService->submissionContext($tenantId, $formId, true);
        $version = $context['version'];

        $validated = $request->validate([
            'field_name' => ['required', 'string', 'max:100'],
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $field = $version->fields->firstWhere('name', $validated['field_name']);
        if (! $field || $field->type !== 'file') {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Campo de anexo inválido ou inexistente.',
                'errors' => ['field_name' => ['O campo informado não é um campo de anexo válido.']],
            ], 422);
        }

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $validated['file'];
        $originalName = $file->getClientOriginalName();
        $ext = strtolower($file->getClientOriginalExtension());

        $prohibited = ['php', 'phtml', 'phar', 'exe', 'bat', 'cmd', 'sh', 'bash', 'js', 'vbs', 'scr', 'bin', 'jar'];
        if (in_array($ext, $prohibited, true) || str_contains($originalName, "\0") || str_contains($originalName, '..')) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Tipo de arquivo não permitido por motivos de segurança.',
                'errors' => ['file' => ['Extensão de arquivo não permitida.']],
            ], 422);
        }

        if (! empty($field->options) && is_array($field->options)) {
            $allowedExts = [];
            foreach ($field->options as $opt) {
                if (isset($opt['value'])) {
                    foreach (explode(',', strtolower($opt['value'])) as $item) {
                        $trimmed = trim($item);
                        if ($trimmed !== '') {
                            $allowedExts[] = $trimmed;
                        }
                    }
                }
            }
            if (! empty($allowedExts) && ! in_array($ext, $allowedExts, true)) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Extensão não permitida para este campo.',
                    'errors' => ['file' => ['Permitidos apenas: '.implode(', ', $allowedExts)]],
                ], 422);
            }
        }

        $safeOriginalName = preg_replace('/[^\p{L}\p{N}_\-\.\s]/u', '', $originalName) ?: 'anexo.'.$ext;
        $hashName = Str::random(40).'.'.$ext;
        $folder = 'tenants/'.$tenantId.'/attachments/'.date('Y/m');
        $path = $file->storeAs($folder, $hashName, 'local');

        return response()->json([
            'success' => true,
            'data' => [
                'path' => $path,
                'name' => $safeOriginalName,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            ],
            'message' => 'Arquivo enviado com sucesso.',
            'errors' => null,
        ], 201);
    }

    public function downloadAttachment(Request $request, int $submissionId, int $fieldId): StreamedResponse
    {
        $tenantId = (int) $request->user()->tenant_id;
        $details = $this->submissionService->details($request->user(), $submissionId);

        $item = collect($details['values'] ?? [])->firstWhere('field_id', $fieldId);
        if (! $item || ($item['type'] ?? '') !== 'file') {
            abort(404, 'Anexo não encontrado.');
        }

        $value = $item['value'] ?? null;
        $path = is_array($value) ? ($value['path'] ?? null) : $value;
        $filename = is_array($value) ? ($value['name'] ?? null) : basename((string) $path);
        $mime = is_array($value) ? ($value['mime_type'] ?? null) : null;

        if (! $path || ! str_starts_with($path, "tenants/{$tenantId}/attachments/") || str_contains($path, '..')) {
            abort(404, 'Anexo inválido.');
        }

        if (! Storage::disk('local')->exists($path)) {
            abort(404, 'Arquivo não encontrado no armazenamento.');
        }

        return Storage::disk('local')->download($path, $filename ?: 'anexo', [
            'Content-Type' => $mime ?: 'application/octet-stream',
        ]);
    }
}

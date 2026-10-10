<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExportSubmissionsRequest;
use App\Http\Requests\FinalizeDraftRequest;
use App\Http\Requests\SaveDraftRequest;
use App\Http\Requests\SubmitFormRequest;
use App\Http\Requests\UpdateDraftRequest;
use App\Services\Forms\SubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionController extends Controller
{
    public function __construct(private readonly SubmissionService $submissionService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->integer('per_page', 20)));
        $paginator = $this->submissionService->listForUser($request->user(), $request->only(['status', 'form_id', 'version_id', 'date_from', 'date_to', 'user_id']), $perPage);
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
            echo $csv['content'];
        }, $csv['filename'], ['Content-Type' => 'text/csv; charset=UTF-8']);
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
}

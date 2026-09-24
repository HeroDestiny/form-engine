<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\FormVersion;
use App\Models\User;
use App\Services\Forms\SubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionController extends Controller
{
    public function __construct(private readonly SubmissionService $submissionService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->integer('per_page', 20)));

        $query = FormSubmission::query()
            ->with(['version.form', 'values', 'version.fields'])
            ->where('tenant_id', $request->user()?->tenant_id);

        $status = $request->string('status')->toString();
        if (in_array($status, ['submitted', 'draft'], true)) {
            $query->where('status', $status);
        } else {
            $query->where('status', 'submitted');
        }

        if ($request->user()?->role === 'user') {
            $query->where('submitted_by', $request->user()?->id);
        }

        if ($request->filled('form_id')) {
            $query->whereHas('version', fn ($q) => $q->where('form_id', (int) $request->integer('form_id')));
        }

        if ($request->filled('version_id')) {
            $query->where('form_version_id', (int) $request->integer('version_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('submitted_at', '>=', $request->string('date_from')->toString());
        }

        if ($request->filled('date_to')) {
            $query->whereDate('submitted_at', '<=', $request->string('date_to')->toString());
        }

        if ($request->filled('user_id') && in_array($request->user()?->role, ['manager', 'admin'], true)) {
            $query->where('submitted_by', (int) $request->integer('user_id'));
        }

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->orderByDesc('submitted_at')->paginate($perPage);

        $submissions = $paginator->getCollection()->map(function (FormSubmission $submission) {
            $owner = User::query()->find($submission->submitted_by);

            return [
                'id' => $submission->id,
                'form' => [
                    'id' => $submission->version->form->id,
                    'name' => $submission->version->form->name,
                ],
                'version' => [
                    'id' => $submission->version->id,
                    'version_number' => $submission->version->version_number,
                ],
                'user' => [
                    'id' => $submission->submitted_by,
                    'name' => $owner?->name,
                    'email' => $owner?->email,
                ],
                'submitted_at' => $submission->submitted_at,
                'fields_count' => $submission->values->count(),
            ];
        })->values();

        $paginator->setCollection($submissions);

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
        $drafts = FormSubmission::query()
            ->where('tenant_id', $request->user()?->tenant_id)
            ->where('status', 'draft')
            ->where('submitted_by', $request->user()?->id)
            ->whereHas('version', fn ($query) => $query->where('form_id', $formId))
            ->orderByDesc('created_at')
            ->get();

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
        $submission = FormSubmission::query()
            ->with(['version.form', 'version.fields', 'values'])
            ->where('tenant_id', $request->user()?->tenant_id)
            ->where('id', $submissionId)
            ->first();

        if (! $submission) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Submissão não encontrada',
                'errors' => null,
            ], 404);
        }

        if ($request->user()?->role === 'user' && $submission->submitted_by !== (int) $request->user()?->id) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Submissão não encontrada',
                'errors' => null,
            ], 404);
        }

        $owner = User::query()->find($submission->submitted_by);

        $values = $submission->version->fields->sortBy('order')->map(function (FormField $field) use ($submission) {
            $value = $submission->values->firstWhere('form_field_id', $field->id)?->value;
            $item = [
                'field_id' => $field->id,
                'label' => $field->label,
                'name' => $field->name,
                'type' => $field->type,
                'value' => $value,
                'is_required' => $field->is_required,
            ];

            if (in_array($field->type, ['select', 'radio'], true) && is_array($field->options)) {
                $selected = collect($field->options)->firstWhere('value', $value);
                $item['value_label'] = $selected['label'] ?? $value;
            }

            return $item;
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'submission' => [
                    'id' => $submission->id,
                    'status' => $submission->status,
                    'submitted_at' => $submission->submitted_at,
                    'created_at' => $submission->created_at,
                ],
                'form' => [
                    'id' => $submission->version->form->id,
                    'name' => $submission->version->form->name,
                    'description' => $submission->version->form->description,
                ],
                'version' => [
                    'id' => $submission->version->id,
                    'version_number' => $submission->version->version_number,
                    'published_at' => $submission->version->published_at,
                ],
                'user' => $owner ? [
                    'id' => $owner->id,
                    'name' => $owner->name,
                    'email' => $owner->email,
                ] : null,
                'values' => $values,
            ],
            'message' => null,
            'errors' => null,
        ]);
    }

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        if (! in_array($request->user()?->role, ['manager', 'admin'], true)) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Você não tem permissão para esta ação',
                'errors' => null,
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'form_id' => ['required', 'integer'],
            'version_ids' => ['nullable', 'array'],
            'version_ids.*' => ['integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'format' => ['nullable', 'in:csv'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Erro de validação',
                'errors' => $validator->errors(),
            ], 422);
        }

        $form = Form::query()
            ->where('id', (int) $request->integer('form_id'))
            ->where('tenant_id', $request->user()?->tenant_id)
            ->first();

        if (! $form) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Nenhuma submissão encontrada para exportar',
                'errors' => null,
            ], 422);
        }

        $submissions = FormSubmission::query()
            ->with(['version.fields', 'values'])
            ->where('tenant_id', $request->user()?->tenant_id)
            ->where('status', 'submitted')
            ->whereHas('version', fn ($q) => $q->where('form_id', $form->id))
            ->when(
                $request->filled('version_ids'),
                fn ($query) => $query->whereIn(
                    'form_version_id',
                    collect((array) $request->input('version_ids'))->map(static fn ($id) => (int) $id)->all()
                )
            )
            ->when(
                $request->filled('date_from'),
                fn ($query) => $query->whereDate('submitted_at', '>=', $request->string('date_from')->toString())
            )
            ->when(
                $request->filled('date_to'),
                fn ($query) => $query->whereDate('submitted_at', '<=', $request->string('date_to')->toString())
            )
            ->orderByDesc('submitted_at')
            ->get();

        if ($submissions->isEmpty()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Nenhuma submissão encontrada para exportar',
                'errors' => null,
            ], 422);
        }

        $headers = ['ID', 'Usuário', 'Email', 'Data de Submissão', 'Versão'];
        $allFieldNames = [];
        foreach ($submissions as $submission) {
            foreach ($submission->version->fields as $field) {
                $allFieldNames[$field->id] = $field->label;
            }
        }
        $headers = array_merge($headers, array_values($allFieldNames));

        $lines = [];
        $lines[] = implode(',', $headers);

        foreach ($submissions as $submission) {
            $owner = User::query()->find($submission->submitted_by);
            $row = [
                $submission->id,
                '"'.str_replace('"', '""', (string) ($owner?->name ?? '')).'"',
                (string) ($owner?->email ?? ''),
                (string) optional($submission->submitted_at)->format('d/m/Y H:i'),
                $submission->version->version_number,
            ];

            foreach (array_keys($allFieldNames) as $fieldId) {
                $field = $submission->version->fields->firstWhere('id', $fieldId);
                $value = $submission->values->firstWhere('form_field_id', $fieldId)?->value;

                if ($field) {
                    $value = $this->submissionService->mapValueForField($field, $value);
                }

                $row[] = '"'.str_replace('"', '""', (string) $value).'"';
            }

            $lines[] = implode(',', $row);
        }

        $csv = "\xEF\xBB\xBF".implode("\n", $lines);
        $filename = 'submissoes-'.$form->id.'-'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function submit(Request $request, int $formId): JsonResponse
    {
        $form = Form::query()
            ->where('id', $formId)
            ->where('tenant_id', $request->user()?->tenant_id)
            ->first();

        if (! $form) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Recurso não encontrado',
                'errors' => null,
            ], 404);
        }

        if (! $form->is_active) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Este formulário não está mais disponível',
                'errors' => null,
            ], 400);
        }

        $version = FormVersion::query()
            ->where('form_id', $formId)
            ->where('is_published', true)
            ->orderByDesc('version_number')
            ->with('fields')
            ->first();

        if (! $version) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Versão não pode ser publicada',
                'errors' => null,
            ], 422);
        }

        $rules = $this->submissionService->buildRules($version);

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Erro de validação',
                'errors' => $validator->errors(),
            ], 422);
        }

        $submission = $this->submissionService->createSubmitted(
            (int) $request->user()?->tenant_id,
            (int) $request->user()?->id,
            $version,
            (array) $request->input('values', []),
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

    public function saveDraft(Request $request, int $formId): JsonResponse
    {
        $form = Form::query()
            ->where('id', $formId)
            ->where('tenant_id', $request->user()?->tenant_id)
            ->first();

        if (! $form || ! $form->is_active) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Este formulário não está mais disponível',
                'errors' => null,
            ], 422);
        }

        $version = FormVersion::query()
            ->where('form_id', $formId)
            ->where('is_published', true)
            ->orderByDesc('version_number')
            ->with('fields')
            ->first();

        $rules = $version ? $this->submissionService->buildRules($version, true) : [];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Erro de validação',
                'errors' => $validator->errors(),
            ], 422);
        }

        $draft = $this->submissionService->createDraft(
            (int) $request->user()?->tenant_id,
            (int) $request->user()?->id,
            $version,
            (array) $request->input('values', []),
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
                'fields_filled' => count((array) $request->input('values', [])),
                'fields_total' => $version?->fields->count() ?? 0,
            ],
            'message' => 'Rascunho salvo com sucesso. Você pode continuar depois.',
            'errors' => null,
        ], 201);
    }
}

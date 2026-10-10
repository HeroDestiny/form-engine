<?php

namespace App\Repositories\Eloquent;

use App\Models\FormSubmission;
use App\Repositories\Contracts\FormSubmissionRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class FormSubmissionRepository implements FormSubmissionRepositoryInterface
{
    public function create(array $data): FormSubmission
    {
        return FormSubmission::query()->create($data);
    }

    public function lockDraft(int $tenantId, int $userId, int $formId, int $submissionId): ?FormSubmission
    {
        return FormSubmission::query()->where('tenant_id', $tenantId)->where('submitted_by', $userId)
            ->where('id', $submissionId)->where('status', 'draft')
            ->whereHas('version', fn ($query) => $query->where('form_id', $formId))
            ->lockForUpdate()->first();
    }

    public function update(FormSubmission $submission, array $data): FormSubmission
    {
        $submission->update($data);

        return $submission->fresh();
    }

    public function paginateForTenant(int $tenantId, ?int $ownerId, array $filters, int $perPage): LengthAwarePaginator
    {
        $status = in_array($filters['status'] ?? '', ['submitted', 'draft'], true) ? $filters['status'] : 'submitted';
        $query = FormSubmission::query()
            ->with(['version.form', 'values', 'version.fields', 'submitter'])
            ->where('tenant_id', $tenantId)
            ->where('status', $status);

        if ($ownerId !== null) {
            $query->where('submitted_by', $ownerId);
        } elseif (! empty($filters['user_id'])) {
            $query->where('submitted_by', (int) $filters['user_id']);
        }
        if (! empty($filters['form_id'])) {
            $query->whereHas('version', fn ($q) => $q->where('form_id', (int) $filters['form_id']));
        }
        if (! empty($filters['version_id'])) {
            $query->where('form_version_id', (int) $filters['version_id']);
        }
        $this->applyDateFilters($query, $filters);

        return $query->orderByDesc($status === 'draft' ? 'created_at' : 'submitted_at')->orderByDesc('id')->paginate($perPage);
    }

    public function listDrafts(int $tenantId, int $userId, int $formId): Collection
    {
        return FormSubmission::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'draft')
            ->where('submitted_by', $userId)
            ->whereHas('version', fn ($query) => $query->where('form_id', $formId))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    public function findInTenant(int $tenantId, int $submissionId): ?FormSubmission
    {
        return FormSubmission::query()
            ->with(['version.form', 'version.fields', 'values', 'submitter'])
            ->where('tenant_id', $tenantId)
            ->where('id', $submissionId)
            ->first();
    }

    public function listForExport(int $tenantId, int $formId, array $filters): Collection
    {
        $query = FormSubmission::query()
            ->with(['version.fields', 'values', 'submitter'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'submitted')
            ->whereHas('version', fn ($q) => $q->where('form_id', $formId));

        if (! empty($filters['version_ids'])) {
            $query->whereIn('form_version_id', $filters['version_ids']);
        }
        $this->applyDateFilters($query, $filters);

        return $query->orderByDesc('submitted_at')->orderByDesc('id')->get();
    }

    private function applyDateFilters(Builder $query, array $filters): void
    {
        $dateColumn = ($filters['status'] ?? '') === 'draft' ? 'created_at' : 'submitted_at';
        if (! empty($filters['date_from'])) {
            $query->whereDate($dateColumn, '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate($dateColumn, '<=', $filters['date_to']);
        }
    }
}

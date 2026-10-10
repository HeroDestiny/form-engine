<?php

namespace App\Repositories\Contracts;

use App\Models\FormSubmission;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface FormSubmissionRepositoryInterface
{
    public function create(array $data): FormSubmission;

    public function lockDraft(int $tenantId, int $userId, int $formId, int $submissionId): ?FormSubmission;

    public function update(FormSubmission $submission, array $data): FormSubmission;

    public function paginateForTenant(int $tenantId, ?int $ownerId, array $filters, int $perPage): LengthAwarePaginator;

    public function listDrafts(int $tenantId, int $userId, int $formId): Collection;

    public function findInTenant(int $tenantId, int $submissionId): ?FormSubmission;

    public function listForExport(int $tenantId, int $formId, array $filters): Collection;
}

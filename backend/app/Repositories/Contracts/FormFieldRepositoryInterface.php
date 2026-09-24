<?php

namespace App\Repositories\Contracts;

use App\Models\FormField;
use Illuminate\Support\Collection;

interface FormFieldRepositoryInterface
{
    public function listForVersionInTenant(int $tenantId, int $formId, int $versionId): Collection;

    public function findInTenant(int $tenantId, int $formId, int $versionId, int $fieldId): ?FormField;

    public function listForVersion(int $versionId): Collection;

    public function create(array $data): FormField;

    public function update(FormField $field, array $data): FormField;

    public function delete(FormField $field): void;
}

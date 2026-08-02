<?php

namespace App\Services\Forms;

use App\Models\Form;
use App\Repositories\Contracts\FormRepositoryInterface;
use App\Repositories\Contracts\FormVersionRepositoryInterface;
use App\Models\FormVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FormService
{
    public function __construct(
        private readonly FormRepositoryInterface $formRepository,
        private readonly FormVersionRepositoryInterface $formVersionRepository,
    ) {}

    public function listAvailableForTenant(int $tenantId, ?string $search = null, string $sort = 'name', string $order = 'asc'): Collection
    {
        $sortColumn = in_array($sort, ['name', 'created_at'], true) ? $sort : 'name';
        $sortDirection = in_array(strtolower($order), ['asc', 'desc'], true) ? strtolower($order) : 'asc';

        return $this->formRepository
            ->listAvailableForTenant($tenantId, $search, $sortColumn, $sortDirection)
            ->map(function (Form $form) {
                $latest = $form->versions->first();

                return [
                    'id' => $form->id,
                    'name' => $form->name,
                    'description' => $form->description,
                    'is_active' => $form->is_active,
                    'latest_version' => $latest ? [
                        'id' => $latest->id,
                        'version_number' => $latest->version_number,
                        'is_published' => $latest->is_published,
                        'published_at' => $latest->published_at,
                        'fields_count' => $latest->fields_count,
                    ] : null,
                    'created_at' => $form->created_at,
                ];
            })
            ->values();
    }

    public function listAllForTenant(int $tenantId, ?string $search = null, string $sort = 'name', string $order = 'asc'): Collection
    {
        $sortColumn = in_array($sort, ['name', 'created_at'], true) ? $sort : 'name';
        $sortDirection = in_array(strtolower($order), ['asc', 'desc'], true) ? strtolower($order) : 'asc';

        return $this->formRepository
            ->listAllForTenant($tenantId, $search, $sortColumn, $sortDirection)
            ->map(function (Form $form) {
                $latest = $form->versions->first();

                return [
                    'id' => $form->id,
                    'name' => $form->name,
                    'description' => $form->description,
                    'is_active' => $form->is_active,
                    'latest_version' => $latest ? [
                        'id' => $latest->id,
                        'version_number' => $latest->version_number,
                        'is_published' => $latest->is_published,
                        'published_at' => $latest->published_at,
                        'fields_count' => $latest->fields_count,
                    ] : null,
                    'created_at' => $form->created_at,
                ];
            })
            ->values();
    }

    public function findDetailedInTenant(int $tenantId, int $formId): ?array
    {
        $form = $this->formRepository->findDetailedInTenant($tenantId, $formId);

        if (! $form) {
            return null;
        }

        $latest = $form->versions->first();

        return [
            'id' => $form->id,
            'name' => $form->name,
            'description' => $form->description,
            'is_active' => $form->is_active,
            'latest_version' => $latest ? [
                'id' => $latest->id,
                'version_number' => $latest->version_number,
                'is_published' => $latest->is_published,
                'published_at' => $latest->published_at,
                'fields_count' => $latest->fields_count,
            ] : null,
            'created_at' => $form->created_at,
            'updated_at' => $form->updated_at,
        ];
    }

    public function createWithInitialVersion(int $tenantId, string $name, ?string $description, int $createdBy): array
    {
        return DB::transaction(function () use ($tenantId, $name, $description, $createdBy) {
            $form = $this->formRepository->create([
                'tenant_id' => $tenantId,
                'name' => $name,
                'description' => $description,
                'is_active' => true,
                'created_by' => $createdBy,
            ]);

            $version = $this->formVersionRepository->create([
                'form_id' => $form->id,
                'version_number' => 1,
                'is_published' => false,
                'published_at' => null,
                'created_by' => $createdBy,
            ]);

            return [
                'form' => $form,
                'version' => $version,
            ];
        });
    }

    public function findInTenant(int $tenantId, int $formId): ?Form
    {
        return $this->formRepository->findInTenant($tenantId, $formId);
    }

    public function updateStatus(Form $form, bool $isActive): Form
    {
        return $this->formRepository->updateStatus($form, $isActive);
    }
}

<?php

namespace App\Repositories\Eloquent;

use App\Models\Form;
use App\Repositories\Contracts\FormRepositoryInterface;
use Illuminate\Support\Collection;

class FormRepository implements FormRepositoryInterface
{
    public function listAvailableForTenant(int $tenantId, ?string $search, string $sort, string $order): Collection
    {
        $query = Form::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereHas('versions', fn ($builder) => $builder->where('is_published', true))
            ->with(['versions' => fn ($builder) => $builder->where('is_published', true)->orderByDesc('version_number')->withCount('fields')]);

        if ($search !== null && $search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return $query->orderBy($sort, $order)->get();
    }

    public function listAllForTenant(int $tenantId, ?string $search, string $sort, string $order): Collection
    {
        $query = Form::query()
            ->where('tenant_id', $tenantId)
            ->with(['versions' => fn ($builder) => $builder->orderByDesc('version_number')->withCount('fields')]);

        if ($search !== null && $search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return $query->orderBy($sort, $order)->get();
    }

    public function findDetailedInTenant(int $tenantId, int $formId): ?Form
    {
        return Form::query()
            ->where('id', $formId)
            ->where('tenant_id', $tenantId)
            ->with(['versions' => fn ($builder) => $builder->where('is_published', true)->orderByDesc('version_number')->withCount('fields')])
            ->first();
    }

    public function create(array $data): Form
    {
        return Form::query()->create($data);
    }

    public function findInTenant(int $tenantId, int $formId): ?Form
    {
        return Form::query()
            ->where('id', $formId)
            ->where('tenant_id', $tenantId)
            ->first();
    }

    public function updateStatus(Form $form, bool $isActive): Form
    {
        $form->update(['is_active' => $isActive]);

        return $form->fresh();
    }
}

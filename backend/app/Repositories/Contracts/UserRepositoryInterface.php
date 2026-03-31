<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    public function paginateForTenant(int $tenantId, int $perPage): LengthAwarePaginator;

    public function listForTenant(int $tenantId): Collection;

    public function create(array $data): User;

    public function findInTenant(int $tenantId, int $userId): ?User;

    public function updateStatus(User $user, bool $isActive): User;
}

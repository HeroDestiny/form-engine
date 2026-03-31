<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TenantUserService
{
    public function __construct(private readonly UserRepositoryInterface $userRepository) {}

    public function listForTenantPaginated(int $tenantId, int $perPage = 20): LengthAwarePaginator
    {
        return $this->userRepository->paginateForTenant($tenantId, $perPage);
    }

    public function listForTenant(int $tenantId): Collection
    {
        return $this->userRepository->listForTenant($tenantId);
    }

    public function create(int $tenantId, string $name, string $email, string $password, string $role): User
    {
        return $this->userRepository->create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'is_active' => true,
        ]);
    }

    public function findInTenant(int $tenantId, int $userId): ?User
    {
        return $this->userRepository->findInTenant($tenantId, $userId);
    }

    public function updateStatus(User $user, bool $isActive): User
    {
        return $this->userRepository->updateStatus($user, $isActive);
    }

    public function toApiPayload(User $user): array
    {
        return $user->only(['id', 'tenant_id', 'name', 'email', 'role', 'is_active', 'created_at', 'updated_at']);
    }
}

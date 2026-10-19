<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Exceptions\NotFoundException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TenantUserService
{
    public function __construct(private readonly UserRepositoryInterface $userRepository, private readonly AuditRecorder $audit) {}

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
        return DB::transaction(function () use ($tenantId, $name, $email, $password, $role) {
            $user = $this->userRepository->create([
                'tenant_id' => $tenantId,
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => $role,
                'is_active' => true,
            ]);
            $this->audit->record('CREATE_USER', $tenantId, 'user', $user->id, ['role' => $role]);

            return $user;
        });
    }

    public function findInTenant(int $tenantId, int $userId): ?User
    {
        return $this->userRepository->findInTenant($tenantId, $userId);
    }

    public function updateStatus(User $user, bool $isActive): User
    {
        return DB::transaction(function () use ($user, $isActive) {
            $updated = $this->userRepository->updateStatus($user, $isActive);
            $this->audit->record($isActive ? 'UPDATE_USER' : 'DEACTIVATE_USER', $user->tenant_id, 'user', $user->id, ['is_active' => $isActive]);

            return $updated;
        });
    }

    public function toApiPayload(User $user): array
    {
        return $user->only(['id', 'tenant_id', 'name', 'email', 'role', 'is_active', 'created_at', 'updated_at']);
    }

    public function changeStatus(int $tenantId, int $userId, int $actorId, bool $isActive): User
    {
        if ($actorId === $userId && ! $isActive) {
            throw new BusinessException('Você não pode desativar sua própria conta', 'forbidden');
        }
        $user = $this->userRepository->findInTenant($tenantId, $userId);
        if (! $user) {
            throw new NotFoundException('Usuário não encontrado');
        }

        return $this->updateStatus($user, $isActive);
    }
}

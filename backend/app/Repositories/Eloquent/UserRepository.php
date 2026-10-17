<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class UserRepository implements UserRepositoryInterface
{
    public function paginateForTenant(int $tenantId, int $perPage): LengthAwarePaginator
    {
        return User::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->paginate($perPage, ['id', 'tenant_id', 'name', 'email', 'role', 'is_active']);
    }

    public function listForTenant(int $tenantId): Collection
    {
        return User::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->get(['id', 'tenant_id', 'name', 'email', 'role', 'is_active']);
    }

    public function create(array $data): User
    {
        return User::query()->create($data);
    }

    public function findInTenant(int $tenantId, int $userId): ?User
    {
        return User::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $userId)
            ->first();
    }

    public function updateStatus(User $user, bool $isActive): User
    {
        $user->update(['is_active' => $isActive]);

        return $user->fresh();
    }

    public function findForLogin(string $email, ?string $tenantSlug): Collection
    {
        return User::query()
            ->with('tenant')
            ->where('email', $email)
            ->when($tenantSlug !== null && $tenantSlug !== '', fn ($query) => $query->whereHas('tenant', fn ($tenantQuery) => $tenantQuery->where('slug', $tenantSlug)))
            ->get();
    }

    public function issueToken(User $user): string
    {
        return $user->createToken('auth-token', ['*'], now()->addMinutes((int) config('sanctum.expiration')))->plainTextToken;
    }

    public function revokeCurrentToken(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}

<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(private readonly UserRepositoryInterface $userRepository) {}

    public function login(array $credentials): array
    {
        $tenantSlug = $credentials['tenant_slug'] ?? null;
        $users = $this->userRepository->findForLogin($credentials['email'], $tenantSlug);

        if ($users->isEmpty()) {
            throw new BusinessException('E-mail ou senha incorretos', 'invalid_credentials');
        }

        if (! $tenantSlug && $users->count() > 1) {
            throw new BusinessException('Erro de validação', errors: [
                'tenant_slug' => ['Informe o tenant para autenticar este e-mail.'],
            ]);
        }

        $user = $users->first(fn (User $candidate) => Hash::check($credentials['password'], (string) $candidate->password));
        if (! $user) {
            throw new BusinessException('E-mail ou senha incorretos', 'invalid_credentials');
        }
        if (! $user->is_active) {
            throw new BusinessException('Sua conta esta desativada. Entre em contato com o administrador.', 'forbidden');
        }
        if (! $user->tenant || ! $user->tenant->is_active) {
            throw new BusinessException('Acesso temporariamente indisponivel. Entre em contato com o suporte.', 'forbidden');
        }

        return [
            'user' => $user,
            'tenant' => $user->tenant,
            'token' => $this->userRepository->issueToken($user),
            'token_type' => 'Bearer',
            'expires_in' => config('sanctum.expiration') ? (int) config('sanctum.expiration') * 60 : 0,
        ];
    }

    public function logout(User $user): void
    {
        $this->userRepository->revokeCurrentToken($user);
    }
}

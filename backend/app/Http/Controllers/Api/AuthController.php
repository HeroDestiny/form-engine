<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'tenant_slug' => ['nullable', 'string', 'regex:/^[a-z0-9-]+$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Dados invalidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenantSlug = $request->filled('tenant_slug') ? $request->string('tenant_slug')->toString() : null;

        $users = User::query()
            ->with('tenant')
            ->where('email', $request->string('email'))
            ->when($tenantSlug, fn ($query) => $query->whereHas('tenant', fn ($tenantQuery) => $tenantQuery->where('slug', $tenantSlug)))
            ->get();

        if ($users->isEmpty()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'E-mail ou senha incorretos',
                'errors' => null,
            ], 401);
        }

        if (! $tenantSlug && $users->count() > 1) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Erro de validação',
                'errors' => [
                    'tenant_slug' => ['Informe o tenant para autenticar este e-mail.'],
                ],
            ], 422);
        }

        $plainPassword = $request->string('password')->toString();
        $user = $users->first(fn (User $candidate) => Hash::check($plainPassword, (string) $candidate->password));

        if (! $user) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'E-mail ou senha incorretos',
                'errors' => null,
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Sua conta esta desativada. Entre em contato com o administrador.',
                'errors' => null,
            ], 403);
        }

        if (! $user->tenant || ! $user->tenant->is_active) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Acesso temporariamente indisponivel. Entre em contato com o suporte.',
                'errors' => null,
            ], 403);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'tenant' => $user->tenant,
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => config('sanctum.expiration') ? (int) config('sanctum.expiration') * 60 : 0,
            ],
            'message' => 'Login realizado com sucesso',
            'errors' => null,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'tenant' => $user?->tenant,
            ],
            'message' => 'Usuario autenticado',
            'errors' => null,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Logout realizado com sucesso',
            'errors' => null,
        ]);
    }
}

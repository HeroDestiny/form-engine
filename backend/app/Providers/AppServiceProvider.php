<?php

namespace App\Providers;

use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\FormFieldRepositoryInterface;
use App\Repositories\Contracts\FormRepositoryInterface;
use App\Repositories\Contracts\FormSubmissionRepositoryInterface;
use App\Repositories\Contracts\FormSubmissionValueRepositoryInterface;
use App\Repositories\Contracts\FormVersionRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\AuditLogRepository;
use App\Repositories\Eloquent\FormFieldRepository;
use App\Repositories\Eloquent\FormRepository;
use App\Repositories\Eloquent\FormSubmissionRepository;
use App\Repositories\Eloquent\FormSubmissionValueRepository;
use App\Repositories\Eloquent\FormVersionRepository;
use App\Repositories\Eloquent\TenantRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Services\AuditRecorder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TenantRepositoryInterface::class, TenantRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(AuditLogRepositoryInterface::class, AuditLogRepository::class);
        $this->app->bind(FormRepositoryInterface::class, FormRepository::class);
        $this->app->bind(FormVersionRepositoryInterface::class, FormVersionRepository::class);
        $this->app->bind(FormFieldRepositoryInterface::class, FormFieldRepository::class);
        $this->app->bind(FormSubmissionRepositoryInterface::class, FormSubmissionRepository::class);
        $this->app->bind(FormSubmissionValueRepositoryInterface::class, FormSubmissionValueRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request): array {
            $response = function (Request $request, array $headers) {
                app(AuditRecorder::class)->record('LOGIN_THROTTLED', null, 'authentication', null,
                    ['retry_after' => (int) ($headers['Retry-After'] ?? 60)]);

                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Muitas tentativas. Tente novamente em '.$headers['Retry-After'].' segundos.',
                    'errors' => null,
                ], 429, $headers);
            };
            // Hash untrusted identifiers: cache keys never contain email/password.
            $account = hash('sha256', json_encode([
                is_string($request->input('email')) ? strtolower($request->input('email')) : '',
                $request->input('tenant_slug'),
            ]));
            $attempts = (int) config('security.login_attempts_per_minute');

            return [
                Limit::perMinute($attempts)->by('ip:'.$request->ip())->response($response),
                Limit::perMinute($attempts)->by('account:'.$account)->response($response),
            ];
        });
    }
}

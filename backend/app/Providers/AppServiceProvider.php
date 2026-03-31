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
        //
    }
}

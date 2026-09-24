<?php

use App\Http\Controllers\Api\AdminTenantController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FormController;
use App\Http\Controllers\Api\FormFieldController;
use App\Http\Controllers\Api\FormVersionController;
use App\Http\Controllers\Api\SubmissionController;
use App\Http\Controllers\Api\TenantUserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::prefix('admin')->middleware('role:admin-sistema')->group(function (): void {
        Route::get('/tenants', [AdminTenantController::class, 'index']);
        Route::post('/tenants', [AdminTenantController::class, 'store']);
        Route::patch('/tenants/{tenantId}/status', [AdminTenantController::class, 'updateStatus']);
    });

    Route::get('/forms', [FormController::class, 'index']);
    Route::post('/forms', [FormController::class, 'storeForCurrentTenant'])->middleware('role:manager,admin');
    Route::get('/forms/{formId}', [FormController::class, 'show']);

    Route::get('/submissions', [SubmissionController::class, 'index']);
    Route::get('/submissions/{submissionId}', [SubmissionController::class, 'show']);
    Route::post('/submissions/export', [SubmissionController::class, 'export']);

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('role:admin');
    Route::get('/audit-logs/{logId}', [AuditLogController::class, 'show'])->middleware('role:admin');

    Route::get('/tenants/{tenantId}/users', [TenantUserController::class, 'index'])->middleware(['tenant.access', 'role:admin']);
    Route::post('/tenants/{tenantId}/users', [TenantUserController::class, 'store'])->middleware(['tenant.access', 'role:admin']);
    Route::patch('/tenants/{tenantId}/users/{userId}/status', [TenantUserController::class, 'updateStatus'])->middleware(['tenant.access', 'role:admin']);

    Route::post('/tenants/{tenantId}/forms', [FormController::class, 'store'])->middleware(['tenant.access', 'role:manager,admin']);
    Route::get('/forms/{formId}/versions', [FormVersionController::class, 'index']);
    Route::post('/forms/{formId}/versions', [FormVersionController::class, 'store'])->middleware('role:manager,admin');
    Route::patch('/forms/{formId}/status', [FormController::class, 'updateStatus'])->middleware('role:manager,admin');

    Route::get('/forms/{formId}/versions/{versionId}/fields', [FormFieldController::class, 'index']);
    Route::post('/forms/{formId}/versions/{versionId}/fields', [FormFieldController::class, 'store'])->middleware('role:manager,admin');
    Route::put('/forms/{formId}/versions/{versionId}/fields/{fieldId}', [FormFieldController::class, 'update'])->middleware('role:manager,admin');
    Route::delete('/forms/{formId}/versions/{versionId}/fields/{fieldId}', [FormFieldController::class, 'destroy'])->middleware('role:manager,admin');
    Route::post('/forms/{formId}/versions/{versionId}/publish', [FormVersionController::class, 'publish'])->middleware('role:manager,admin');

    Route::get('/forms/{formId}/drafts', [SubmissionController::class, 'listDrafts']);
    Route::post('/forms/{formId}/submit', [SubmissionController::class, 'submit']);
    Route::post('/forms/{formId}/drafts', [SubmissionController::class, 'saveDraft']);
});

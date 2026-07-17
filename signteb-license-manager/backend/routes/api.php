<?php

use App\Http\Controllers\Api\V1\PluginLicenseController;
use App\Http\Controllers\Api\V1\PluginUpdateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public plugin API (consumed by the SignTeb WordPress SDK)
|--------------------------------------------------------------------------
| throttle:plugin-public  → per-IP limit (config: slm.rate_limits)
| throttle:plugin-license → per-license-key limit, defined in AppServiceProvider
*/
Route::prefix('v1/plugin')->middleware(['throttle:plugin-public'])->group(function () {
    Route::post('activate', [PluginLicenseController::class, 'activate']);
    Route::post('validate', [PluginLicenseController::class, 'validateLicense'])
        ->middleware('throttle:plugin-license');
    Route::post('deactivate', [PluginLicenseController::class, 'deactivate']);

    Route::get('check-update', [PluginUpdateController::class, 'checkUpdate']);
    Route::get('download', [PluginUpdateController::class, 'download'])->name('plugin.download');
});

/*
|--------------------------------------------------------------------------
| MEDORA AI service API (Phase 7) — JWT + license auth, Redis token quotas.
| Routes registered here; controllers land with the Phase 7 milestone.
|--------------------------------------------------------------------------
| POST v1/ai/chat        POST v1/ai/lead-score     POST v1/ai/summarize
| POST v1/ai/pdf-export  POST v1/ai/sheets-sync    GET  v1/ai/usage
*/

/*
|--------------------------------------------------------------------------
| Customer portal API (Phase 5) — JWT auth:customer guard.
|--------------------------------------------------------------------------
| GET  v1/me/licenses         POST v1/me/licenses/{uuid}/transfer
| GET  v1/me/invoices         GET  v1/me/downloads
| POST v1/payments/init       GET  v1/payments/callback/{gateway}
*/

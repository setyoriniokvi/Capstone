<?php

use App\Http\Controllers\Api\AutomationController;
use Illuminate\Support\Facades\Route;

// F57 — integrasi workflow automation (n8n)
Route::middleware(['automation', 'throttle:60,1'])->prefix('automation')->group(function () {
    Route::post('reminders/claim', [AutomationController::class, 'claim']);
    Route::post('reminders/{dispatch}/report', [AutomationController::class, 'report']);
});

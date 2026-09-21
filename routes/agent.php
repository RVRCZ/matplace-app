<?php

use App\Http\Controllers\Farm\AgentController;
use App\Http\Controllers\Farm\CreditController;
use Illuminate\Support\Facades\Route;

/*
 * Machine-to-machine endpoints: no session, no cookies, no CSRF (registered outside the `web` group in bootstrap/app.php).
 *   /api/agent/*            farm-agent, bearer token per agent
 *   /webhooks/payments/*    payment gateway, verified by signature
 */
Route::prefix('api/agent')->name('agent.')->middleware(['farm.agent', 'throttle:240,1'])->group(function () {
    Route::post('sync', [AgentController::class, 'sync'])->name('sync');
    Route::post('commands/{command}/result', [AgentController::class, 'commandResult'])->name('commands.result');
    Route::get('jobs/{job}/gcode', [AgentController::class, 'gcode'])->name('jobs.gcode');
    Route::post('printers/{key}/snapshot', [AgentController::class, 'snapshot'])->name('printers.snapshot');
});

Route::post('webhooks/payments/{name}', [CreditController::class, 'webhook'])->middleware('throttle:120,1')->name('webhooks.payments');

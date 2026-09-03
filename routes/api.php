<?php

use App\Http\Controllers\Api\V1\SubscriberController;
use App\Http\Controllers\Api\V1\TransactionalEmailController;
use App\Http\Controllers\AutomationTriggerController;
use App\Http\Middleware\AuthenticateAutomationTrigger;
use App\Http\Middleware\AuthenticateTeamApiKey;
use Illuminate\Support\Facades\Route;

Route::post('automations/{automation}/trigger', [AutomationTriggerController::class, 'store'])
    ->middleware(['throttle:automation-trigger', AuthenticateAutomationTrigger::class])
    ->name('automations.trigger');

Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(['throttle:team-api-auth', AuthenticateTeamApiKey::class, 'throttle:team-api'])
    ->group(function (): void {
        Route::post('transactional-emails/{transactionalEmail}/send', [TransactionalEmailController::class, 'store'])
            ->name('transactional-emails.send');

        Route::post('audiences/{audience}/subscribers', [SubscriberController::class, 'store'])
            ->name('audiences.subscribers.store');
        Route::delete('audiences/{audience}/subscribers', [SubscriberController::class, 'destroy'])
            ->name('audiences.subscribers.destroy');
    });

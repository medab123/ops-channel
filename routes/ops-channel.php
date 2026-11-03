<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Medox\OpsChannel\Http\Controllers\OpenSessionController;
use Medox\OpsChannel\Http\Controllers\RunCommandController;
use Medox\OpsChannel\Http\Middleware\VerifySignature;

/*
| The two-step protocol. A challenge is issued by the first endpoint and
| answered at the second, so the signature itself is the authentication —
| which is why these sit outside any service-to-service auth group.
*/

$throttle = (string) config('ops-channel.routes.session_throttle', '30,1');

Route::post('session', OpenSessionController::class)
    ->middleware($throttle === '' ? [] : ["throttle:{$throttle}"])
    ->name('session');

Route::post('sync', RunCommandController::class)
    ->middleware(VerifySignature::class)
    ->name('sync');

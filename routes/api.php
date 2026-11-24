<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\PingController;
use Illuminate\Support\Facades\Route;

// Laravel adds the `api` prefix and middleware group to this file.
Route::prefix('v1')->name('api.v1.')->middleware('throttle:api')->group(function (): void {
    Route::get('ping', PingController::class)->name('ping');
});

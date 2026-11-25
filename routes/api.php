<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Identity\ConfirmPhoneController;
use App\Http\Controllers\Api\V1\Identity\DeleteAccountController;
use App\Http\Controllers\Api\V1\Identity\SaveDeviceTokenController;
use App\Http\Controllers\Api\V1\Identity\ShowAccountController;
use App\Http\Controllers\Api\V1\Identity\SignInWithAppleController;
use App\Http\Controllers\Api\V1\Identity\SignInWithGoogleController;
use App\Http\Controllers\Api\V1\Identity\SignOutController;
use App\Http\Controllers\Api\V1\Identity\StartPhoneVerificationController;
use App\Http\Controllers\Api\V1\Identity\UpdateAccountController;
use App\Http\Middleware\EnsureAccountIsActive;
use Illuminate\Support\Facades\Route;

// Laravel adds the `api` prefix and middleware group to this file.
Route::prefix('v1')->name('api.v1.')->middleware('throttle:api')->group(function (): void {
    Route::post('auth/google', SignInWithGoogleController::class)->name('auth.google');
    Route::post('auth/apple', SignInWithAppleController::class)->name('auth.apple');

    // Every route here answers for the token's own account: none takes another user's id.
    Route::middleware(['auth:sanctum', EnsureAccountIsActive::class])->group(function (): void {
        Route::post('auth/sign-out', SignOutController::class)->name('auth.sign-out');

        Route::get('me', ShowAccountController::class)->name('me.show');
        Route::patch('me', UpdateAccountController::class)->name('me.update');
        Route::delete('me', DeleteAccountController::class)->name('me.destroy');
        Route::post('me/phone', StartPhoneVerificationController::class)->name('me.phone.start');
        Route::post('me/phone/confirm', ConfirmPhoneController::class)->name('me.phone.confirm');
        Route::put('me/device-token', SaveDeviceTokenController::class)->name('me.device-token');
    });
});

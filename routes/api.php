<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Identity\ConfirmPhoneController;
use App\Http\Controllers\Api\V1\Identity\DeleteAccountController;
use App\Http\Controllers\Api\V1\Identity\DeleteAvatarController;
use App\Http\Controllers\Api\V1\Identity\SaveDeviceTokenController;
use App\Http\Controllers\Api\V1\Identity\ShowAccountController;
use App\Http\Controllers\Api\V1\Identity\SignInWithAppleController;
use App\Http\Controllers\Api\V1\Identity\SignInWithGoogleController;
use App\Http\Controllers\Api\V1\Identity\SignOutController;
use App\Http\Controllers\Api\V1\Identity\StartPhoneVerificationController;
use App\Http\Controllers\Api\V1\Identity\UpdateAccountController;
use App\Http\Controllers\Api\V1\Identity\UpdateAvatarController;
use App\Http\Controllers\Api\V1\Media\DeleteUploadController;
use App\Http\Controllers\Api\V1\Media\ShowUploadController;
use App\Http\Controllers\Api\V1\Media\StoreUploadController;
use App\Http\Middleware\EnsureAccountIsActive;
use Illuminate\Support\Facades\Route;

// Laravel adds the `api` prefix and middleware group to this file.
Route::prefix('v1')->name('api.v1.')->middleware('throttle:api')->group(function (): void {
    Route::post('auth/google', SignInWithGoogleController::class)->name('auth.google');
    Route::post('auth/apple', SignInWithAppleController::class)->name('auth.apple');

    // Every route here answers for the token's own account. The only ids they take are the
    // account's own uploads, checked by PendingUploadPolicy.
    Route::middleware(['auth:sanctum', EnsureAccountIsActive::class])->group(function (): void {
        Route::post('auth/sign-out', SignOutController::class)->name('auth.sign-out');

        Route::get('me', ShowAccountController::class)->name('me.show');
        Route::patch('me', UpdateAccountController::class)->name('me.update');
        Route::delete('me', DeleteAccountController::class)->name('me.destroy');
        Route::post('me/phone', StartPhoneVerificationController::class)->name('me.phone.start');
        Route::post('me/phone/confirm', ConfirmPhoneController::class)->name('me.phone.confirm');
        Route::put('me/device-token', SaveDeviceTokenController::class)->name('me.device-token');
        Route::put('me/avatar', UpdateAvatarController::class)->middleware('throttle:uploads')->name('me.avatar.update');
        Route::delete('me/avatar', DeleteAvatarController::class)->name('me.avatar.destroy');

        Route::post('uploads', StoreUploadController::class)->middleware('throttle:uploads')->name('uploads.store');
        // Someone else's upload answers 404, as a missing one (PendingUploadPolicy).
        Route::get('uploads/{upload}', ShowUploadController::class)->name('uploads.show');
        Route::delete('uploads/{upload}', DeleteUploadController::class)->name('uploads.destroy');
    });
});

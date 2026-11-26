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
use App\Http\Controllers\Api\V1\RealEstate\ArchivePropertyController;
use App\Http\Controllers\Api\V1\RealEstate\ContactPropertyOwnerController;
use App\Http\Controllers\Api\V1\RealEstate\ListOwnPropertiesController;
use App\Http\Controllers\Api\V1\RealEstate\ListPropertiesController;
use App\Http\Controllers\Api\V1\RealEstate\MarkRentedPropertyController;
use App\Http\Controllers\Api\V1\RealEstate\MarkSoldPropertyController;
use App\Http\Controllers\Api\V1\RealEstate\PausePropertyController;
use App\Http\Controllers\Api\V1\RealEstate\ResumePropertyController;
use App\Http\Controllers\Api\V1\RealEstate\ShowPropertyController;
use App\Http\Controllers\Api\V1\RealEstate\ShowRealEstateOptionsController;
use App\Http\Controllers\Api\V1\RealEstate\StorePropertyController;
use App\Http\Controllers\Api\V1\RealEstate\SubmitPropertyController;
use App\Http\Controllers\Api\V1\RealEstate\UpdatePropertyController;
use App\Http\Middleware\EnsureAccountIsActive;
use Illuminate\Support\Facades\Route;

// Laravel adds the `api` prefix and middleware group to this file.
Route::prefix('v1')->name('api.v1.')->middleware('throttle:api')->group(function (): void {
    Route::post('auth/google', SignInWithGoogleController::class)->name('auth.google');
    Route::post('auth/apple', SignInWithAppleController::class)->name('auth.apple');

    // Public: published properties, and a signed-in owner's own through the same route.
    Route::get('properties', ListPropertiesController::class)->name('properties.index');
    Route::get('properties/{property}', ShowPropertyController::class)->name('properties.show');
    Route::get('real-estate/options', ShowRealEstateOptionsController::class)->name('real-estate.options');

    // Every route here answers for the token's own account. The ids they take are checked by
    // their policies: the account's own uploads (PendingUploadPolicy) and properties (PropertyPolicy).
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

        Route::get('me/properties', ListOwnPropertiesController::class)->name('me.properties');
        Route::post('properties', StorePropertyController::class)->name('properties.store');
        Route::patch('properties/{property}', UpdatePropertyController::class)->name('properties.update');
        Route::post('properties/{property}/submit', SubmitPropertyController::class)->name('properties.submit');
        Route::post('properties/{property}/pause', PausePropertyController::class)->name('properties.pause');
        Route::post('properties/{property}/resume', ResumePropertyController::class)->name('properties.resume');
        Route::post('properties/{property}/sold', MarkSoldPropertyController::class)->name('properties.sold');
        Route::post('properties/{property}/rented', MarkRentedPropertyController::class)->name('properties.rented');
        Route::post('properties/{property}/archive', ArchivePropertyController::class)->name('properties.archive');
        Route::post('properties/{property}/contact', ContactPropertyOwnerController::class)->middleware('throttle:contact')->name('properties.contact');
    });
});

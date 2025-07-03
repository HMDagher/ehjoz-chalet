<?php

declare(strict_types=1);

use App\Http\Controllers\pageController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

Route::controller(pageController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('chalets', 'chalets')->name('chalets');
    Route::get('contact', 'contact')->name('contact');
    Route::post('contact', 'sendContact')->name('contact.send');
    Route::get('booking/confirmation/{bookingReference}', 'bookingConfirmation')->name('booking-confirmation');
    Route::get('/{slug}', 'roomDetailSOne')->name('chalet-details');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::post('/signin', [AuthController::class, 'signin'])->name('signin');
Route::post('/signup', [AuthController::class, 'signup'])->name('signup');
Route::get('/login', [pageController::class, 'index'])->name('login');

// API routes for chalet booking
Route::prefix('api')->group(function () {
    Route::get('/chalet/{slug}/availability', [App\Http\Controllers\Api\ChaletApiController::class, 'getAvailability']);
    Route::post('/chalet/{slug}/calculate-price', [App\Http\Controllers\Api\ChaletApiController::class, 'calculatePrice']);
    Route::post('/bookings', [App\Http\Controllers\Api\BookingApiController::class, 'store']);
    Route::get('/user/check-auth', [App\Http\Controllers\Api\BookingApiController::class, 'checkAuth']);
    Route::get('/bookings/consecutive-slot-combinations', [App\Http\Controllers\Api\BookingApiController::class, 'consecutiveSlotCombinations']);
    Route::get('/chalet/{slug}/available-dates', [App\Http\Controllers\Api\ChaletApiController::class, 'getAvailableDates']);
});
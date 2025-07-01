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
    Route::get('/{slug}', 'roomDetailSOne')->name('chalet-details');
    Route::get('contact', 'contact')->name('contact');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::post('/signin', [AuthController::class, 'signin'])->name('signin');
Route::post('/signup', [AuthController::class, 'signup'])->name('signup');
Route::get('/login', [pageController::class, 'index'])->name('login');

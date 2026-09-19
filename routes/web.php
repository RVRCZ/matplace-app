<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Api\CalculationController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\ModelFileController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\CalculatorController;
use App\Http\Controllers\Printer\PrinterController;
use App\Http\Controllers\Printer\QuoteController;
use Illuminate\Support\Facades\Route;

// ── Public: the one screen ───────────────────────────────────────────────────
Route::get('/', [CalculatorController::class, 'index'])->name('home');
Route::get('/c/{calculation}', [CalculatorController::class, 'share'])->name('calc.share');

// Public quote (online version of the PDF) — no account needed
Route::get('/q/{quote}', [QuoteController::class, 'publicShow'])->name('quote.public');
Route::get('/q/{quote}/pdf', [QuoteController::class, 'publicPdf'])->name('quote.public.pdf');
Route::post('/q/{quote}/accept', [QuoteController::class, 'accept'])->name('quote.accept');
Route::post('/q/{quote}/decline', [QuoteController::class, 'decline'])->name('quote.decline');

// ── JSON API used by the calculator ──────────────────────────────────────────
Route::prefix('api')->name('api.')->group(function () {
    Route::get('config', [ConfigController::class, 'show'])->name('config');
    Route::post('uploads', [UploadController::class, 'store'])->middleware('throttle:uploads')->name('uploads.store');
    Route::get('files/{modelFile}', [UploadController::class, 'show'])->name('files.show');
    Route::get('files/{modelFile}/model.stl', [ModelFileController::class, 'stl'])->name('files.stl');
    Route::post('calculations', [CalculationController::class, 'store'])->middleware('throttle:calculations')->name('calculations.store');
    Route::get('calculations/{calculation}', [CalculationController::class, 'show'])->name('calculations.show');
});

// ── Auth ─────────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::get('/forgot-password', [AuthController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendReset'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->middleware('throttle:6,1')->name('password.update');
    Route::get('/auth/{provider}/redirect', [OAuthController::class, 'redirect'])->name('oauth.redirect');
    Route::get('/auth/{provider}/callback', [OAuthController::class, 'callback'])->name('oauth.callback');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// ── Account (any role) ───────────────────────────────────────────────────────
Route::middleware('auth')->prefix('account')->name('account')->group(function () {
    Route::get('/', [AccountController::class, 'index']);
    Route::get('/profile', [AccountController::class, 'profile'])->name('.profile');
    Route::post('/profile', [AccountController::class, 'updateProfile'])->name('.profile.update');
    Route::post('/roles/{role}/enable', [AccountController::class, 'enableRole'])->name('.roles.enable');
    Route::post('/roles/{role}/disable', [AccountController::class, 'disableRole'])->name('.roles.disable');
});

// ── Printer tools (role switch "I own a printer") ────────────────────────────
Route::middleware(['auth', 'role:printer'])->prefix('printer')->name('printer.')->group(function () {
    Route::get('/', [PrinterController::class, 'dashboard'])->name('dashboard');
    Route::get('/profile', [PrinterController::class, 'profile'])->name('profile');
    Route::post('/profile', [PrinterController::class, 'updateProfile'])->name('profile.update');
    Route::get('/calculator', [PrinterController::class, 'calculator'])->name('calculator');
    Route::get('/calculator/{calculation}', [PrinterController::class, 'calculator'])->name('calculator.open');

    Route::get('/quotes', [QuoteController::class, 'index'])->name('quotes');
    Route::post('/quotes', [QuoteController::class, 'store'])->name('quotes.store');
    Route::get('/quotes/{quote}', [QuoteController::class, 'edit'])->name('quotes.edit');
    Route::post('/quotes/{quote}', [QuoteController::class, 'update'])->name('quotes.update');
    Route::post('/quotes/{quote}/send', [QuoteController::class, 'send'])->name('quotes.send');
    Route::post('/quotes/{quote}/duplicate', [QuoteController::class, 'duplicate'])->name('quotes.duplicate');
    Route::get('/quotes/{quote}/pdf', [QuoteController::class, 'pdf'])->name('quotes.pdf');
});

<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Generic webhook endpoint that logs all incoming messages.
Route::any('/webhook', WebhookController::class)
    ->withoutMiddleware([VerifyCsrfToken::class]);

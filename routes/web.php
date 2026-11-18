<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\VideoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function (): void {
    Route::get('auth/csrf', fn (Request $request) => response()
        ->json(['token' => $request->session()->token()])
        ->header('Cache-Control', 'no-store'));

    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('public/videos/{id}', [VideoController::class, 'shared']);

    Route::middleware('auth')->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::apiResource('videos', VideoController::class)->parameters(['videos' => 'id']);
    });
});

Route::get('/uploads/{filename}', [VideoController::class, 'media'])
    ->where('filename', '[a-zA-Z0-9._-]+');

Route::get('/{path?}', function () {
    $index = public_path('spa/index.html');
    abort_unless(is_file($index), 503, 'Das React-Frontend wurde noch nicht gebaut. Führe npm run build aus.');

    return response()->file($index, ['Cache-Control' => 'no-cache']);
})->where('path', '(?:login|register|videos(?:/[^/]+)?|view/[^/]+)');

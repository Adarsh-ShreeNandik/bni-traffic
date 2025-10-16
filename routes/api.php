<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\RelevantController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::post('login', [UserController::class, 'login']);

Route::middleware(['auth:api'])->group(function () {
    Route::get('users', [UserController::class, 'fetchUsers']);
    Route::get('events', [EventController::class, 'index']);
    Route::get('relevants', [RelevantController::class, 'index']);
    Route::post('change-password', [UserController::class, 'changePassword']);
    Route::post('import-users', [ImportController::class, 'importUsers']);
    Route::post('import-events', [ImportController::class, 'importEvents']);
    Route::post('import-relevants', [ImportController::class, 'importRelevant']);
    Route::post('logout', [ImportController::class, 'logout']);
});

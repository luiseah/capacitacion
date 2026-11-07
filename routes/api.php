<?php

use App\Http\Controllers\Api\V1\RequestController;
use App\Http\Controllers\Api\V1\RequestTypeController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->middleware('auth:sanctum')->group(function () {
    Route::get('user', fn (Request $request) => Response::api($request->user(), __('Successful')))->name('user');

    Route::get('request-types', [RequestTypeController::class, 'index'])->name('request-types.index');
    Route::get('request-types/{type}', [RequestTypeController::class, 'show'])->name('request-types.show');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    Route::get('requests', [RequestController::class, 'index'])->name('requests.index');
    Route::post('requests/{type}', [RequestController::class, 'store'])->name('requests.store');
    Route::get('requests/{serviceRequest}', [RequestController::class, 'show'])->name('requests.show');
    Route::put('requests/{serviceRequest}', [RequestController::class, 'update'])->name('requests.update');
    Route::delete('requests/{serviceRequest}', [RequestController::class, 'destroy'])->name('requests.destroy');
});

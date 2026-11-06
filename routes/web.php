<?php

use App\Http\Controllers\Web\RequestController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::prefix('requests')->name('requests.')->group(function () {
        Route::get('/', [RequestController::class, 'index'])->name('index');
        Route::get('create', [RequestController::class, 'create'])->name('create');
        Route::get('{type}/create', [RequestController::class, 'form'])->name('form');
        Route::get('{serviceRequest}', [RequestController::class, 'show'])->name('show');
        Route::get('{serviceRequest}/edit', [RequestController::class, 'edit'])->name('edit');
    });
});

require __DIR__.'/settings.php';

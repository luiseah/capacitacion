<?php

use Illuminate\Support\Facades\Route;
use Module\Agreements\Http\Controllers\Web\AgreementController;

Route::get('', [AgreementController::class, 'index'])->name('index');
Route::get('create', [AgreementController::class, 'create'])->name('create');
Route::get('{agreement}/edit', [AgreementController::class, 'edit'])->name('edit');

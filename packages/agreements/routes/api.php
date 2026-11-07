<?php

use Illuminate\Support\Facades\Route;
use Module\Agreements\Http\Controllers\Api\AgreementController;

// Before `agreements/{agreement}`: it is not an id.
Route::get('institution', [AgreementController::class, 'ofInstitution'])->name('institution');

Route::get('', [AgreementController::class, 'index'])->name('index');
Route::post('', [AgreementController::class, 'store'])->name('store');
Route::get('{agreement}', [AgreementController::class, 'show'])->name('show');
Route::put('{agreement}', [AgreementController::class, 'update'])->name('update');
Route::delete('{agreement}', [AgreementController::class, 'destroy'])->name('destroy');
Route::get('{agreement}/document', [AgreementController::class, 'document'])->name('document');
Route::post('{agreement}/document', [AgreementController::class, 'storeDocument'])->name('document.store');
Route::delete('{agreement}/document', [AgreementController::class, 'destroyDocument'])->name('document.destroy');

<?php

use Illuminate\Support\Facades\Route;
use Module\AcademicMobility\Http\Controllers\Web\AgreementController;
use Module\AcademicMobility\Http\Controllers\Web\InstitutionController;
use Module\AcademicMobility\Http\Controllers\Web\MobilityController;
use Module\AcademicMobility\Http\Controllers\Web\RequirementController;

Route::get('mobilities', [MobilityController::class, 'index'])->name('mobilities.index');
Route::get('mobilities/create', [MobilityController::class, 'create'])->name('mobilities.create');
Route::get('mobilities/{mobility}', [MobilityController::class, 'show'])->name('mobilities.show');

Route::get('institutions', [InstitutionController::class, 'index'])->name('institutions.index');
Route::get('institutions/create', [InstitutionController::class, 'create'])->name('institutions.create');
Route::get('institutions/{institution}/edit', [InstitutionController::class, 'edit'])->name('institutions.edit');

Route::get('requirements', [RequirementController::class, 'index'])->name('requirements.index');
Route::get('requirements/create', [RequirementController::class, 'create'])->name('requirements.create');
Route::get('requirements/{requirement}/edit', [RequirementController::class, 'edit'])->name('requirements.edit');

Route::get('agreements', [AgreementController::class, 'index'])->name('agreements.index');
Route::get('agreements/create', [AgreementController::class, 'create'])->name('agreements.create');
Route::get('agreements/{agreement}/edit', [AgreementController::class, 'edit'])->name('agreements.edit');

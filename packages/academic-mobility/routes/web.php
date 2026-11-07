<?php

use Illuminate\Support\Facades\Route;
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

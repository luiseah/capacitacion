<?php

use Illuminate\Support\Facades\Route;
use Module\AcademicMobility\Http\Controllers\Api\InstitutionController;
use Module\AcademicMobility\Http\Controllers\Api\MobilityController;
use Module\AcademicMobility\Http\Controllers\Api\RequirementController;

Route::get('mobilities', [MobilityController::class, 'index'])->name('mobilities.index');
Route::get('mobilities/options', [MobilityController::class, 'options'])->name('mobilities.options');
Route::get('mobilities/requirements', [MobilityController::class, 'requirementsPreview'])->name('mobilities.requirements');
Route::get('mobilities/participants', [MobilityController::class, 'participants'])->name('mobilities.participants');
Route::get('mobilities/participants/sample', [MobilityController::class, 'sampleParticipant'])->name('mobilities.participants.sample');
Route::post('mobilities/participants', [MobilityController::class, 'storeParticipant'])->name('mobilities.participants.store');
Route::get('mobilities/institutions', [MobilityController::class, 'institutions'])->name('mobilities.institutions');
Route::post('mobilities', [MobilityController::class, 'store'])->name('mobilities.store');
Route::get('mobilities/{mobility}', [MobilityController::class, 'show'])->name('mobilities.show');

Route::get('institutions', [InstitutionController::class, 'index'])->name('institutions.index');
Route::post('institutions', [InstitutionController::class, 'store'])->name('institutions.store');
Route::get('institutions/{institution}', [InstitutionController::class, 'show'])->name('institutions.show');
Route::put('institutions/{institution}', [InstitutionController::class, 'update'])->name('institutions.update');
Route::delete('institutions/{institution}', [InstitutionController::class, 'destroy'])->name('institutions.destroy');

Route::get('requirements', [RequirementController::class, 'index'])->name('requirements.index');
Route::post('requirements', [RequirementController::class, 'store'])->name('requirements.store');
Route::get('requirements/{requirement}', [RequirementController::class, 'show'])->name('requirements.show');
Route::put('requirements/{requirement}', [RequirementController::class, 'update'])->name('requirements.update');
Route::delete('requirements/{requirement}', [RequirementController::class, 'destroy'])->name('requirements.destroy');

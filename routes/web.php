<?php

use App\Http\Controllers\Ai\AiSnomedController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/satusehat/questioner-response', [\App\Http\Controllers\SatuSehat\QuestionerResponseController::class, 'index'])->name('satusehat.questioner-response.index');
Route::get('/satusehat/patient/{nik}', [\App\Http\Controllers\SatuSehat\PatientController::class, 'getPatientWithNik']);
Route::get('/satusehat/practitioner/{nik}', [\App\Http\Controllers\SatuSehat\PractitionerController::class, 'getPractitionerByNik']);
Route::post('/satusehat/questionnaire-response/send', [\App\Http\Controllers\SatuSehat\QuestionerResponseController::class, 'store'])->name('satusehat.questioner-response.store');

Route::post('/ai/snomed-map', [AiSnomedController::class, 'aiSnomedMap'])
    ->name('ai.snomed.map');
Route::get('/satusehat/allergy-intolerance', [\App\Http\Controllers\SatuSehat\AlergyIntoleranceController::class, 'index'])->name('satusehat.allergy-intolerance.index');
Route::post('/satusehat/allergy-intolerance/send', [\App\Http\Controllers\SatuSehat\AlergyIntoleranceController::class, 'store'])->name('satusehat.allergy-intolerance.store');

Route::get('/snomed-ct/allergy-intolerance', [\App\Http\Controllers\SatuSehat\AlergyIntoleranceController::class, 'snomedCt'])->name('snomed-ct.allergy-intolerance');

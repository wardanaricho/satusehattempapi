<?php

namespace App\Http\Controllers\SatuSehat;

use App\Http\Controllers\Controller;
use App\Services\SatuSehat\Patient\SatuSehatPatientService;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    public function getPatientWithNik($nik, SatuSehatPatientService $patientService)
    {
        if (!$nik) {
            return response()->json([
                'error' => 'Query parameter "nik" wajib diisi.'
            ], 422);
        }

        $data = $patientService->getPatientByNik($nik);
        return response()->json($data);
    }
}

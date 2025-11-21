<?php

namespace App\Http\Controllers\SatuSehat;

use App\Http\Controllers\Controller;
use App\Services\SatuSehat\Practitioner\SatuSehatPractitionerService;
use Illuminate\Http\Request;

class PractitionerController extends Controller
{
    public function getPractitionerByNik($nik, SatuSehatPractitionerService $practitionerService)
    {
        if (!$nik) {
            return response()->json([
                'error' => 'Query parameter "nik" wajib diisi.'
            ], 422);
        }

        $data = $practitionerService->getPractitionerByNik($nik);
        return response()->json($data);
    }
}

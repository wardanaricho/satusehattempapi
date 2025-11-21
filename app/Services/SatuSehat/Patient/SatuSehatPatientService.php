<?php

namespace App\Services\SatuSehat\Patient;

use App\Services\SatuSehat\Auth\SatuSehatAuthService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class SatuSehatPatientService
{
    public function __construct(
        protected SatuSehatAuthService $auth,
        protected string $fhirBaseUrl = ''
    ) {
        $this->fhirBaseUrl = $this->fhirBaseUrl ?: rtrim(Config::get('satusehat.fhir.base_url'), '/');
    }

    public function getPatientByNik(string $nik): ?array
    {
        $res = Http::withToken($this->auth->getAccessToken())
            ->accept('application/fhir+json')
            ->get($this->fhirBaseUrl . '/Patient', [
                'identifier' => 'https://fhir.kemkes.go.id/id/nik|' . $nik,
            ]);

        if ($res->failed()) {
            throw new RequestException($res);
        }

        $json = $res->json();
        return $json['entry'][0]['resource'] ?? null;
    }
}

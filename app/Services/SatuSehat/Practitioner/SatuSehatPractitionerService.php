<?php

namespace App\Services\SatuSehat\Practitioner;

use App\Services\SatuSehat\Auth\SatuSehatAuthService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\Client\RequestException;

class SatuSehatPractitionerService
{
    public function __construct(
        protected SatuSehatAuthService $auth,
        protected string $fhirBaseUrl = ''
    ) {
        $this->fhirBaseUrl = $this->fhirBaseUrl ?: rtrim(Config::get('satusehat.fhir.base_url'), '/');
    }

    public function getPractitionerByNik(string $nik): ?array
    {
        $res = Http::withToken($this->auth->getAccessToken())
            ->accept('application/fhir+json')
            ->get($this->fhirBaseUrl . '/Practitioner', [
                'identifier' => 'https://fhir.kemkes.go.id/id/nik|' . $nik,
            ]);

        if ($res->failed()) {
            throw new RequestException($res);
        }

        $json = $res->json();
        return $json['entry'][0]['resource'] ?? null; // null kalau tidak ketemu
    }
}

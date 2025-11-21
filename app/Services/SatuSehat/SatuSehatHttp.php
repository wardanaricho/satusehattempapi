<?php

namespace App\Services\SatuSehat;

use App\Services\SatuSehat\Auth\SatuSehatAuthService;
use Illuminate\Support\Facades\Http;

class SatuSehatHttp
{
    public function __construct(private SatuSehatAuthService $auth) {}

    public function fhir()
    {
        return Http::withToken($this->auth->getAccessToken())
            ->acceptJson()
            ->baseUrl(rtrim(config('satusehat.fhir.base_url'), '/'));
    }

    public function consent()
    {
        return Http::withToken($this->auth->getAccessToken())
            ->acceptJson()
            ->baseUrl(rtrim(config('satusehat.consent.base_url'), '/'));
    }
}

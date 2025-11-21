<?php

$env = env('SATU_SEHAT_ENV', 'stg');
$isProd = $env === 'prod';

return [
    'env' => $env,

    'auth' => [
        'base_url' => $isProd
            ? env('SATU_SEHAT_AUTH_BASE_PROD', 'https://api-satusehat.kemkes.go.id')
            : env('SATU_SEHAT_AUTH_BASE_STG',  'https://api-satusehat-stg.dto.kemkes.go.id'),
        'token_path'    => '/oauth2/v1/accesstoken',
        'client_id'     => env('SATU_SEHAT_CLIENT_ID'),
        'client_secret' => env('SATU_SEHAT_CLIENT_SECRET'),
    ],

    'fhir' => [
        'base_url' => $isProd
            ? env('SATU_SEHAT_FHIR_BASE_PROD', 'https://api-satusehat.kemkes.go.id/fhir-r4/v1')
            : env('SATU_SEHAT_FHIR_BASE_STG',  'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1'),
    ],

    'consent' => [
        'base_url' => $isProd
            ? env('SATU_SEHAT_CONSENT_BASE_PROD', 'https://api-satusehat.kemkes.go.id/consent/v1')
            : env('SATU_SEHAT_CONSENT_BASE_STG',  'https://api-satusehat-stg.dto.kemkes.go.id/consent/v1'),
    ],

    'organization_id' => env('SATU_SEHAT_ORGANIZATION_ID'),
    'expiry_skew'     => (int) env('SATU_SEHAT_EXPIRY_SKEW', 90),
];

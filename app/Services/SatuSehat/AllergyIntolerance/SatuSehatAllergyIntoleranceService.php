<?php

namespace App\Services\SatuSehat\AllergyIntolerance;

use App\Models\SatuSehat\Local\SatuSehatAllergyIntolerance;
use App\Services\SatuSehat\Auth\SatuSehatAuthService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SatuSehatAllergyIntoleranceService
{
    protected string $fhirBaseUrl;
    protected string $orgId;

    public function __construct(
        protected SatuSehatAuthService $auth,
        ?string $fhirBaseUrl = null,
        ?string $orgId = null,
    ) {
        $this->fhirBaseUrl = rtrim($fhirBaseUrl ?: Config::get('satusehat.fhir.base_url'), '/');
        $this->orgId       = $orgId ?: (string) Config::get('satusehat.organization_id');
    }

    public function buildPayload(array $validated): array
    {
        // Helper untuk ambil string pertama dari array/string
        $firstString = function ($v, $default = ''): string {
            if (is_array($v)) {
                foreach ($v as $item) {
                    $s = is_string($item) ? trim($item) : '';
                    if ($s !== '') return $s;
                }
                return $default;
            }
            return is_string($v) ? trim($v) : $default;
        };

        // Map input -> single values
        $allergyCode  = $firstString($validated['snomedct_allerged'] ?? null);
        $allergyName  = $firstString($validated['alergi'] ?? null);
        // category bisa array (FHIR: medication|food|environment|biologic)
        $categories   = array_values(array_filter(array_map('trim', (array)($validated['category'] ?? []))));
        if (empty($categories)) {
            $categories = ['medication']; // fallback aman
        }

        // recordedDate dari recorded_at
        $recordedAt = trim((string)($validated['recorded_at'] ?? ''));

        // Build encounter display yang readable
        try {
            $dt = \Carbon\Carbon::parse($recordedAt)->locale('id');
            $encDisplay = sprintf(
                'Kunjungan %s di hari %s',
                (string)($validated['patient_name'] ?? ''),
                $dt->isoFormat('dddd, D MMMM YYYY')
            );
        } catch (\Throwable $e) {
            $encDisplay = sprintf(
                'Kunjungan %s di hari %s',
                (string)($validated['patient_name'] ?? ''),
                $recordedAt
            );
        }

        return [
            "resourceType" => "AllergyIntolerance",

            "identifier" => [[
                "system" => "http://sys-ids.kemkes.go.id/allergy/{$this->orgId}",
                "use"    => "official",
                // NOTE: idealnya unique per resource (mis. gabung orgId + no_rawat)
                "value"  => $validated['no_rawat'], // atau "{$this->orgId}|{$validated['no_rawat']}"
            ]],

            "clinicalStatus" => [
                "coding" => [[
                    "system"  => "http://terminology.hl7.org/CodeSystem/allergyintolerance-clinical",
                    "code"    => "active",
                    "display" => "Active",
                ]]
            ],

            "verificationStatus" => [
                "coding" => [[
                    "system"  => "http://terminology.hl7.org/CodeSystem/allergyintolerance-verification",
                    "code"    => "confirmed",
                    "display" => "Confirmed",
                ]]
            ],

            // kirim sebagai array (FHIR mengizinkan multiple)
            "category" => $categories,

            "code" => [
                "coding" => [[
                    "system"  => "http://snomed.info/sct",
                    "code"    => $allergyCode,
                    "display" => $allergyName,
                ]],
                "text" => $allergyName !== '' ? "Alergi {$allergyName}" : "Alergi",
            ],

            "patient" => [
                "reference" => "Patient/{$validated['patient_id']}",
                "display"   => (string) $validated['patient_name'],
            ],

            "encounter" => [
                "reference" => "Encounter/{$validated['encounter_uuid']}",
                "display"   => $encDisplay,
            ],

            // gunakan recorded_at langsung (ISO 8601)
            "recordedDate" => $recordedAt,

            "recorder" => [
                "reference" => "Practitioner/{$validated['practitioner_id']}",
            ],
        ];
    }

    public function sendAndStore(array $validated): array
    {
        $payload = $this->buildPayload($validated);
        $url     = $this->fhirBaseUrl . '/AllergyIntolerance';

        $response = Http::withToken($this->auth->getAccessToken())
            ->accept('application/fhir+json')
            ->asJson()
            ->timeout(15)
            ->post($url, $payload);

        if ($response->failed()) {
            $json   = $response->json();
            $status = $response->status();

            $fhirMessage = $json['issue'][0]['diagnostics']
                ?? ($json['issue'][0]['details']['text'] ?? null)
                ?? ($json['error'] ?? null)
                ?? 'Kesalahan tidak diketahui dari API SatuSehat.';

            Log::warning('Kirim AllergyIntolerance gagal', [
                'status'   => $status,
                'url'      => $url,
                'message'  => $fhirMessage,
                'payload'  => $payload,
                'response' => $json,
            ]);

            throw new RequestException($response);
        }

        $data = $response->json();

        // --- Mapping aman dari payload/response ke kolom DB ---
        $identifierSystem = $payload['identifier'][0]['system']  ?? null;
        $identifierValue  = $payload['identifier'][0]['value']   ?? null;

        $resourceType     = $payload['resourceType']             ?? 'AllergyIntolerance';
        $encRef           = $payload['encounter']['reference']   ?? null; // e.g. "Encounter/uuid"
        $patRef           = $payload['patient']['reference']     ?? null; // e.g. "Patient/xxx"
        $pracRef          = $payload['recorder']['reference']    ?? null; // FHIR: recorder -> Practitioner/xxx

        $categoryArr      = (array)($payload['category'] ?? []);         // array of string
        $categoryStr      = implode(',', array_filter(array_map('trim', $categoryArr))) ?: null;

        $clinicalStatus   = $payload['clinicalStatus']['coding'][0]['code']       ?? null;
        $verificationStat = $payload['verificationStatus']['coding'][0]['code']   ?? null;

        $codeSystem       = $payload['code']['coding'][0]['system']   ?? 'http://snomed.info/sct';
        $codeCode         = $payload['code']['coding'][0]['code']     ?? null;
        $codeDisplay      = $payload['code']['coding'][0]['display']  ?? null;
        $codeText         = $payload['code']['text']                  ?? null;

        $recordedDateIso  = $payload['recordedDate'] ?? null; // ISO-8601 string
        // meta.lastUpdated dari response (jika ada)
        $metaLastUpdated  = $data['meta']['lastUpdated'] ?? null;

        // id resource dari response (fallback: uuid baru)
        $resourceId       = $data['id'] ?? \Illuminate\Support\Str::uuid()->toString();

        // simpan no_rawat asli dari validated (bukan dari payload)
        $noRawat          = $validated['no_rawat'] ?? null;

        // Upsert berbasis identifier_value agar idempotent
        \App\Models\SatuSehat\Local\SatuSehatAllergyIntolerance::updateOrCreate(
            ['identifier_value' => (string)($identifierValue ?? '')],
            [
                'id'                      => $resourceId,
                'no_rawat'                => $noRawat,
                'encounter_reference'     => $encRef,
                'patient_reference'       => $patRef,
                'practitioner_reference'  => $pracRef,
                'identifier_system'       => $identifierSystem,
                'identifier_value'        => $identifierValue,
                'resource_type'           => $resourceType,
                'category'                => $categoryStr,
                'clinical_status'         => $clinicalStatus,
                'verification_status'     => $verificationStat,
                'code_system'             => $codeSystem,
                'code_code'               => $codeCode,
                'code_display'            => $codeDisplay,
                'code_text'               => $codeText,
                'recorded_date'           => $recordedDateIso, // cast datetime oleh model
                'meta_last_updated'       => $metaLastUpdated, // cast datetime oleh model
                'response_json'           => $data,
            ]
        );

        Log::info('✅ AllergyIntolerance berhasil dikirim & disimpan', [
            'identifier_value' => $identifierValue,
            'id'               => $resourceId,
            'status'           => $clinicalStatus,
        ]);

        return $data;
    }
}

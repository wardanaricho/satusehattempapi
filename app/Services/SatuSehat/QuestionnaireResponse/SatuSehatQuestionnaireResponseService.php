<?php

namespace App\Services\SatuSehat\QuestionnaireResponse;

use App\Models\SatuSehat\Local\SatuSehatQuestionnaireResponse as QRModel;
use App\Services\SatuSehat\Auth\SatuSehatAuthService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SatuSehatQuestionnaireResponseService
{
    protected string $fhirBaseUrl;

    public function __construct(
        protected SatuSehatAuthService $auth,
        ?string $fhirBaseUrl = null
    ) {
        $this->fhirBaseUrl = rtrim($fhirBaseUrl ?: Config::get('satusehat.fhir.base_url'), '/');
    }

    /**
     * Bangun payload FHIR QuestionnaireResponse dari input valid.
     */
    public function buildPayload(array $validated): array
    {
        return [
            "resourceType"  => "QuestionnaireResponse",
            "questionnaire" => "https://fhir.kemkes.go.id/Questionnaire/Q0002",
            "status"        => "completed",
            "subject"       => [
                "reference" => "Patient/{$validated['patient_id']}",
                "display"   => $validated['patient_name'],
            ],
            "encounter" => [
                "reference" => "Encounter/{$validated['encounter_uuid']}",
            ],
            "authored" => $validated['authored_date'],
            "author"   => [
                "reference" => "Practitioner/{$validated['practitioner_id']}",
            ],
            "source"   => [
                "reference" => "Patient/{$validated['patient_id']}",
            ],
            "item" => [[
                "linkId" => "1",
                "text"   => "Persyaratan Administrasi",
                "item"   => [
                    [
                        "linkId" => "1.1",
                        "text"   => "Apakah nama, umur, jenis kelamin, berat badan dan tinggi badan pasien sudah sesuai?",
                        "answer" => [[
                            "valueCoding" => [
                                "system"  => "http://terminology.kemkes.go.id/CodeSystem/clinical-term",
                                "code"    => "OV000052",
                                "display" => "Sesuai",
                            ]
                        ]]
                    ],
                    [
                        "linkId" => "1.2",
                        "text"   => "Apakah nama, nomor ijin, alamat dan paraf dokter sudah sesuai?",
                        "answer" => [[
                            "valueCoding" => [
                                "system"  => "http://terminology.kemkes.go.id/CodeSystem/clinical-term",
                                "code"    => "OV000052",
                                "display" => "Sesuai",
                            ]
                        ]]
                    ],
                    [
                        "linkId" => "1.3",
                        "text"   => "Apakah tanggal resep sudah sesuai?",
                        "answer" => [[
                            "valueCoding" => [
                                "system"  => "http://terminology.kemkes.go.id/CodeSystem/clinical-term",
                                "code"    => "OV000052",
                                "display" => "Sesuai",
                            ]
                        ]]
                    ],
                    [
                        "linkId" => "1.4",
                        "text"   => "Apakah ruangan/unit asal resep sudah sesuai?",
                        "answer" => [[
                            "valueCoding" => [
                                "system"  => "http://terminology.kemkes.go.id/CodeSystem/clinical-term",
                                "code"    => "OV000052",
                                "display" => "Sesuai",
                            ]
                        ]]
                    ],


                    [
                        "linkId" => "2",
                        "text"   => "Persyaratan Farmasetik",
                        "item"   => [
                            [
                                "linkId" => "2.1",
                                "text"   => "Apakah nama obat, bentuk dan kekuatan sediaan sudah sesuai?",
                                "answer" => [[
                                    "valueCoding" => [
                                        "system"  => "http://terminology.kemkes.go.id/CodeSystem/clinical-term",
                                        "code"    => "OV000052",
                                        "display" => "Sesuai"
                                    ]
                                ]]
                            ],
                            [
                                "linkId" => "2.2",
                                "text"   => "Apakah dosis dan jumlah obat sudah sesuai?",
                                "answer" => [[
                                    "valueCoding" => [
                                        "system"  => "http://terminology.kemkes.go.id/CodeSystem/clinical-term",
                                        "code"    => "OV000052",
                                        "display" => "Sesuai"
                                    ]
                                ]]
                            ],
                            [
                                "linkId" => "2.3",
                                "text"   => "Apakah stabilitas obat sudah sesuai?",
                                "answer" => [[
                                    "valueCoding" => [
                                        "system"  => "http://terminology.kemkes.go.id/CodeSystem/clinical-term",
                                        "code"    => "OV000052",
                                        "display" => "Sesuai"
                                    ]
                                ]]
                            ],
                            [
                                "linkId" => "2.4",
                                "text"   => "Apakah aturan dan cara penggunaan obat sudah sesuai?",
                                "answer" => [[
                                    "valueCoding" => [
                                        "system"  => "http://terminology.kemkes.go.id/CodeSystem/clinical-term",
                                        "code"    => "OV000052",
                                        "display" => "Sesuai"
                                    ]
                                ]]
                            ],
                        ],
                    ],

                    [
                        "linkId" => "3",
                        "text"   => "Persyaratan Klinis",
                        "item"   => [
                            [
                                "linkId" => "3.1",
                                "text"   => "Apakah ketepatan indikasi, dosis, dan waktu penggunaan obat sudah sesuai?",
                                "answer" => [[
                                    "valueCoding" => [
                                        "system"  => "http://terminology.kemkes.go.id/CodeSystem/clinical-term",
                                        "code"    => "OV000052",
                                        "display" => "Sesuai"
                                    ]
                                ]]
                            ],
                            [
                                "linkId" => "3.2",
                                "text"   => "Apakah terdapat duplikasi pengobatan?",
                                "answer" => [[
                                    "valueBoolean" => false
                                ]]
                            ],
                            [
                                "linkId" => "3.3",
                                "text"   => "Apakah terdapat alergi dan reaksi obat yang tidak dikehendaki (ROTD)?",
                                "answer" => [[
                                    "valueBoolean" => false
                                ]]
                            ],
                            [
                                "linkId" => "3.4",
                                "text"   => "Apakah terdapat kontraindikasi pengobatan?",
                                "answer" => [[
                                    "valueBoolean" => false
                                ]]
                            ],
                            [
                                "linkId" => "3.5",
                                "text"   => "Apakah terdapat dampak interaksi obat?",
                                "answer" => [[
                                    "valueBoolean" => false
                                ]]
                            ],
                        ],
                    ],
                ],
            ]],
        ];
    }

    /**
     * Kirim ke FHIR, lalu simpan ke DB (upsert berdasarkan no_rawat).
     * Melempar RequestException jika HTTP gagal.
     */
    public function sendAndStore(array $validated): array
    {
        $payload = $this->buildPayload($validated);

        $url = $this->fhirBaseUrl . '/QuestionnaireResponse';

        // === Penting: sama seperti PatientService ===
        // getAccessToken() mengembalikan STRING token → langsung masukkan ke withToken()
        $response = Http::withToken($this->auth->getAccessToken())
            ->accept('application/fhir+json')
            ->asJson() // Content-Type: application/json
            ->timeout(15)
            ->post($url, $payload);

        if ($response->failed()) {
            $json   = $response->json();
            $status = $response->status();

            $fhirMessage = $json['issue'][0]['diagnostics']
                ?? $json['issue'][0]['details']['text']
                ?? $json['error']
                ?? 'Kesalahan tidak diketahui dari API SatuSehat.';

            Log::warning('Kirim QuestionnaireResponse gagal', [
                'status'   => $status,
                'url'      => $url,
                'message'  => $fhirMessage,
                'payload'  => $payload,
                'response' => $json,
            ]);

            // Biar konsisten dengan Laravel Http Client — lempar RequestException
            throw new RequestException($response);
        }

        $data = $response->json();

        // Upsert ke DB
        QRModel::updateOrCreate(
            ['no_rawat' => $validated['no_rawat']],
            [
                'id'                     => $data['id'] ?? Str::uuid(),
                'questionnaire'          => $data['questionnaire'] ?? $payload['questionnaire'],
                'encounter_reference'    => $payload['encounter']['reference'],
                'patient_reference'      => $payload['subject']['reference'],
                'practitioner_reference' => $payload['author']['reference'],
                'identifier_system'      => $data['identifier']['system'] ?? null,
                'identifier_value'       => $data['identifier']['value'] ?? null,
                'status'                 => $data['status'] ?? 'completed',
                'authored_at'            => $payload['authored'],
                'response_json'          => $data,
            ]
        );

        Log::info('✅ QuestionnaireResponse berhasil dikirim & disimpan', [
            'no_rawat' => $validated['no_rawat'],
            'id'       => $data['id'] ?? null,
            'status'   => $data['status'] ?? 'completed',
        ]);

        return $data;
    }
}

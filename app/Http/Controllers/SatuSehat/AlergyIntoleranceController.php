<?php

namespace App\Http\Controllers\SatuSehat;

use App\Http\Controllers\Controller;
use App\Models\RegPeriksa;
use App\Models\SnomedAllerged;
use App\Services\SatuSehat\AllergyIntolerance\SatuSehatAllergyIntoleranceService;
use Carbon\Carbon;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class AlergyIntoleranceController extends Controller
{
    public function snomedCt(Request $request)
    {
        // Cari display/code mengandung kata kunci, batasi hasil agar responsif
        $q     = trim((string) $request->input('search', ''));
        $limit = (int) $request->input('limit', 25);
        if ($limit <= 0 || $limit > 100) $limit = 25;

        $snomedCtAllerged = SnomedAllerged::query()
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('display', 'LIKE', "%{$q}%")
                        ->orWhere('code', 'LIKE', "%{$q}%");
                });
            })
            ->orderBy('display')
            ->limit($limit)
            ->get(['code', 'display', 'category']);

        return response()->json($snomedCtAllerged);
    }

    public function index(Request $request)
    {
        $from = $request->input('from') ?? Carbon::now()->toDateString();
        $to   = $request->input('to')   ?? Carbon::now()->toDateString();

        $regPeriksa = RegPeriksa::query()
            ->whereHas('satuSehatEncounter')
            ->whereHas(
                'pemeriksaanRalan',
                fn($q) =>
                $q->whereNotNull('alergi')
                    ->where('alergi', '!=', '')
                    ->where('alergi', '!=', '-')
                    ->where('alergi', '!=', '--')
                    ->whereRaw('LOWER(alergi) != ?', ['tidak ada'])
                    ->whereRaw('LOWER(alergi) != ?', ['lupa'])
                    ->whereRaw('LOWER(alergi) != ?', ['tidak'])
                    ->whereRaw('LOWER(alergi) != ?', ['tidak d ada'])
                    ->whereRaw('LOWER(alergi) != ?', ['tidak ada alergi'])
            )
            ->with([
                'pasien:no_rkm_medis,nm_pasien,no_ktp',
                'dokter.pegawai:nik,no_ktp',
                'satuSehatEncounter:no_rawat,id_encounter',
                'questionnaireResponse:no_rawat,id',
                'pemeriksaanRalan' => fn($q) =>
                $q->select('no_rawat', 'alergi')
                    ->whereNotNull('alergi')
                    ->where('alergi', '!=', '')
                    ->whereRaw('LOWER(alergi) != ?', ['tidak ada']),
            ])
            ->when($from && $to, fn($q) => $q->whereBetween('tgl_registrasi', [$from, $to]))
            ->where('status_lanjut', 'Ralan')
            ->select(['no_rawat', 'no_rkm_medis', 'kd_dokter', 'tgl_registrasi', 'jam_reg'])
            ->orderByDesc('tgl_registrasi')
            ->get();

        return view('satu-sehat.alergy-intolerance.index', compact('regPeriksa'));
    }

    public function store(Request $request, SatuSehatAllergyIntoleranceService $svc)
    {
        // Bersihkan input utama yang rawan spasi berlebih dari sisi client
        $request->merge([
            'no_rawat'        => preg_replace('/\s+/', ' ', (string) $request->input('no_rawat', '')),
            'patient_id'      => preg_replace('/\s+/', ' ', (string) $request->input('patient_id', '')),
            'practitioner_id' => preg_replace('/\s+/', ' ', (string) $request->input('practitioner_id', '')),
            'encounter_uuid'  => preg_replace('/\s+/', ' ', (string) $request->input('encounter_uuid', '')),
            'patient_name'    => preg_replace('/\s+/', ' ', (string) $request->input('patient_name', '')),
            'recorded_at'     => preg_replace('/\s+/', ' ', (string) $request->input('recorded_at', '')),
        ]);

        try {
            // Validasi: semuanya required; arrays + tiap item string
            $validated = $request->validate([
                'alergi'               => 'required|array|min:1',
                'alergi.*'             => 'required|string',

                'snomedct_allerged'    => 'required|array|min:1',
                'snomedct_allerged.*'  => 'required|string',

                'category'             => 'required|array|min:1',
                'category.*'           => 'required|string',

                'no_rawat'         => 'required|string',
                'patient_id'       => 'required|string',
                'practitioner_id'  => 'required|string',
                'encounter_uuid'   => 'required|string',
                'patient_name'     => 'required|string',
                'recorded_at'      => 'required|string',
            ]);

            // Kirim & simpan (service sudah memetakan semua kolom DB)
            $result = $svc->sendAndStore($validated);

            return response()->json([
                'success'     => true,
                'message'     => 'AllergyIntolerance berhasil dikirim ke SatuSehat.',
                'resource_id' => $result['id'] ?? null,
                'meta'        => $result['meta'] ?? null,
            ], 201);
        } catch (ValidationException $e) {
            // Laravel otomatis return 422 jika via web, tapi kita pastikan JSON utk AJAX
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $e->errors(),
            ], 422);
        } catch (RequestException $e) {
            // Ambil detail error FHIR yang informatif
            $json   = $e->response?->json() ?? [];
            $status = $e->response?->status() ?? 502;

            $fhirMessage = $json['issue'][0]['diagnostics']
                ?? ($json['issue'][0]['details']['text'] ?? null)
                ?? ($json['error'] ?? null)
                ?? 'Gagal kirim ke SatuSehat.';

            Log::warning('❌ Gagal kirim AllergyIntolerance ke SatuSehat', [
                'status'   => $status,
                'message'  => $fhirMessage,
                'response' => $json,
            ]);

            return response()->json([
                'success' => false,
                'message' => $fhirMessage,
                'fhir'    => $json,
            ], $status);
        } catch (Throwable $e) {
            Log::error('❌ Error tak terduga AllergyIntolerance', [
                'error' => $e->getMessage(),
                // Hindari kirim trace ke client; cukup log
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan tak terduga.',
            ], 500);
        }
    }
}

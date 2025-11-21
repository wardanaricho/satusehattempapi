<?php

namespace App\Http\Controllers\SatuSehat;

use App\Http\Controllers\Controller;
use App\Models\RegPeriksa;
use App\Models\SatuSehat\Local\SatuSehatQuestionnaireResponse;
use App\Services\SatuSehat\Auth\SatuSehatAuthService;
use App\Services\SatuSehat\QuestionnaireResponse\SatuSehatQuestionnaireResponseService;
use Carbon\Carbon;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QuestionerResponseController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->input('from') ?? Carbon::now()->toDateString();
        $to   = $request->input('to') ?? Carbon::now()->toDateString();

        $regPeriksa = RegPeriksa::query()
            ->has('satuSehatEncounter')
            ->with([
                'pasien:no_rkm_medis,nm_pasien,no_ktp',
                'dokter.pegawai:nik,no_ktp',
                'satuSehatEncounter:no_rawat,id_encounter',
                'questionnaireResponse:no_rawat,id'
            ])
            ->when($from && $to, fn($q) => $q->whereBetween('tgl_registrasi', [$from, $to]))
            ->select(['no_rawat', 'no_rkm_medis', 'kd_dokter', 'tgl_registrasi', 'jam_reg'])
            ->orderByDesc('tgl_registrasi')
            ->where('status_lanjut', 'Ralan')
            ->get();

        return view('satu-sehat.questioner-response.index', compact('regPeriksa'));
    }

    public function store(Request $request, SatuSehatQuestionnaireResponseService $qrService)
    {
        $validated = $request->validate([
            'no_rawat'         => 'required|string',
            'patient_id'       => 'required|string',
            'practitioner_id'  => 'required|string',
            'encounter_uuid'   => 'required|string',
            'patient_name'     => 'required|string',
            'authored_date'    => 'required|string',
        ]);

        try {
            $data = $qrService->sendAndStore($validated);

            return redirect()
                ->back()
                ->with('success', '✅ Berhasil kirim & simpan QuestionnaireResponse (ID: ' . ($data['id'] ?? '-') . ')');
        } catch (RequestException $e) {
            $resp   = $e->response;
            $json   = $resp?->json() ?? [];
            $status = $resp?->status() ?? 500;

            $fhirMessage = $json['issue'][0]['diagnostics']
                ?? $json['issue'][0]['details']['text']
                ?? $json['error']
                ?? $e->getMessage();

            Log::error('❌ Gagal kirim QuestionnaireResponse', [
                'status'   => $status,
                'message'  => $fhirMessage,
                'response' => $json,
            ]);


            return redirect()
                ->back()
                ->with('error', "❌ Gagal kirim ke SatuSehat: {$fhirMessage}");
        } catch (\Throwable $e) {
            Log::error('❌ Error tak terduga saat kirim QuestionnaireResponse', [
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->with('error', '❌ Kesalahan internal. ' . $e->getMessage());
        }
    }
}

<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Services\SatuSehat\AllergyIntolerance\AllergyAiMapperService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiSnomedController extends Controller
{
    public function map(Request $request, AllergyAiMapperService $svc)
    {
        $data = $request->validate([
            'alergi_text' => 'required|string', // string tunggal per baris
            // optional: no_rawat/patient info kalau mau context tambahan
        ]);

        $result = $svc->mapToSnomed($data['alergi_text']);

        return response()->json($result);
    }

    public function aiSnomedMap(Request $request)
    {
        $data = $request->validate([
            'alergi_text' => 'required|string|min:1',
        ]);

        $alergiRaw = trim($data['alergi_text']);

        // 1) Ambil kandidat lokal dari DB (boleh sesuaikan nama kolom/tabel)
        $terms = collect(preg_split('/[,;|\/]+|\s+/', mb_strtolower($alergiRaw)))
            ->map(fn($t) => trim($t))
            ->filter()
            ->unique()
            ->take(5)          // batasi istilah
            ->values();

        $candidates = \App\Models\SnomedAllerged::query()
            ->when($terms->isNotEmpty(), function ($q) use ($terms) {
                $q->where(function ($qq) use ($terms) {
                    foreach ($terms as $t) {
                        $qq->orWhere('display', 'LIKE', "%{$t}%");
                        $qq->orWhere('code', 'LIKE', "%{$t}%");
                    }
                });
            })
            ->limit(30)
            ->get(['code', 'display'])        // kalau kamu punya "category" di tabel, ikutkan juga
            ->map(fn($r) => ['code' => (string)$r->code, 'display' => (string)$r->display])
            ->values()
            ->all();

        // 2) Siapkan prompt & schema JSON
        $system = <<<SYS
Kamu pemetaan SNOMED CT untuk AllergyIntolerance.category="medication"/"food"/"environment"/"biologic".
Diberi teks bebas dari rekam medis Indonesia. Pilih SATU kode SNOMED paling relevan dari daftar kandidat lokal.
Jika tidak ada kandidat yang layak, kembalikan code=null.
Kategori:
- Obat/obatan → "medication"
- Makanan/seafood/telur/susu → "food"
- Debu/dingin/cuaca/polusi → "environment"
Jika teks bermakna "tidak ada alergi", balas code=null.
Output HARUS JSON dengan field: code (string|null), display (string|null), category (string), confidence (0..1).
SYS;

        $user = [
            'alergi_text' => $alergiRaw,
            'candidates'  => $candidates,   // list {code,display}
        ];

        // 3) Panggil OpenAI (via HTTP native Laravel)
        $apiKey = config('services.openai.api_key');
        $model  = config('services.openai.model');
        $base   = config('services.openai.base'); // ex: https://api.openai.com

        try {
            $resp = Http::withToken($apiKey)
                ->baseUrl($base)
                ->asJson()
                ->post('/v1/chat/completions', [
                    'model' => $model,
                    'temperature' => 0.0,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user',   'content' => json_encode($user, JSON_UNESCAPED_UNICODE)],
                    ],
                ]);

            if ($resp->failed()) {
                Log::error('AI map failed', ['status' => $resp->status(), 'body' => $resp->body()]);
                return response()->json([
                    'success' => false,
                    'reason'  => 'ai_failed',
                    'message' => 'Layanan AI gagal dipanggil.',
                ], 500);
            }

            $json = $resp->json();
            $rawContent = $json['choices'][0]['message']['content'] ?? '{}';
            $parsed = json_decode($rawContent, true);

            // Normalisasi respons
            $match = [
                'code'       => $parsed['code']       ?? null,
                'display'    => $parsed['display']    ?? null,
                'category'   => $parsed['category']   ?? 'medication',
                'confidence' => (float)($parsed['confidence'] ?? 0.0),
            ];

            // Jika tidak ada match yang masuk akal
            if (empty($match['code']) || empty($match['display'])) {
                return response()->json([
                    'success' => true,
                    'match'   => null, // biar UI tahu tidak ada kandidat
                    'reason'  => $parsed['reason'] ?? 'no_match',
                ]);
            }

            return response()->json([
                'success' => true,
                'match'   => $match,
            ]);
        } catch (\Throwable $e) {
            Log::error('AI exception', ['msg' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'reason'  => 'exception',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}

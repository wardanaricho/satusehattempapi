<?php

namespace App\Services\SatuSehat\AllergyIntolerance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AllergyAiMapperService
{
    protected string $openAiKey;
    protected string $openAiModel;

    public function __construct()
    {
        $this->openAiKey   = (string) env('OPENAI_API_KEY');
        $this->openAiModel = (string) (env('OPENAI_MODEL', 'gpt-4o-mini'));
    }

    public function mapToSnomed(string $raw): array
    {
        $clean = $this->normalize($raw);
        if ($this->looksNegative($clean)) {
            return [
                'match' => null,
                'reason' => 'negative/allergy none',
                'confidence' => 0.0,
                'candidates' => [],
            ];
        }

        // 1) tarik kandidat dari DB lokal
        $cands = $this->candidateSearch($clean);

        if (empty($cands)) {
            return [
                'match' => null,
                'reason' => 'no candidate',
                'confidence' => 0.0,
                'candidates' => [],
            ];
        }

        // 2) panggil LLM untuk memilih SALAH SATU kandidat
        $picked = $this->askLlm($clean, $cands);

        return [
            'match' => $picked,      // ['code','display','category','confidence','why']
            'candidates' => $cands,  // buat debugging di FE jika mau
        ];
    }

    protected function normalize(string $s): string
    {
        $x = Str::of($s)->lower();
        $x = Str::of(preg_replace('/[,;|\/]+/u', ',', $x));
        $x = Str::of(preg_replace('/\s+/u', ' ', (string) $x))->trim();
        // sinonim ringkas
        $map = [
            'amox' => 'amoxicillin',
            'amoxicilin' => 'amoxicillin',
            'amoxcilin' => 'amoxicillin',
            'pinisilin' => 'penicillin',
            'penicilin' => 'penicillin',
            'asmef' => 'asam mefenamat',
            'mefinal' => 'asam mefenamat',
            'ibu profen' => 'ibuprofen',
            'cipro' => 'ciprofloxacin',
            'ciproflaxacin' => 'ciprofloxacin',
            'antalgin' => 'metamizole',
        ];
        foreach ($map as $k => $v) {
            $x = Str::of(str_replace($k, $v, (string)$x));
        }
        return (string)$x;
    }

    protected function looksNegative(string $x): bool
    {
        $neg = [
            'tidak ada alergi',
            'tidak ada alergi obat',
            'tidak ada alergi makanan',
            'tidak ada alergi obat dan makanan',
            'tdak ada',
            'tidak d ada',
            'tidak tahu',
            'tidak diketahui',
            'no allergy',
            'none',
            '0',
            '1',
            '77',
            '80',
            '99'
        ];
        foreach ($neg as $p) {
            if (Str::contains($x, $p)) return true;
        }
        return false;
    }

    protected function candidateSearch(string $q): array
    {
        // pecah token sederhana untuk LIKE
        $tokens = collect(explode(',', $q))
            ->flatMap(fn($s) => preg_split('/\s+/u', trim($s)))
            ->filter(fn($t) => $t !== '' && mb_strlen($t) >= 3)
            ->unique()
            ->take(6)
            ->values();

        $query = DB::table('snomed_allerged') // kolom: code, display, category?
            ->select('code', 'display', 'category')
            ->when($tokens->isNotEmpty(), function ($qq) use ($tokens) {
                $qq->where(function ($w) use ($tokens) {
                    foreach ($tokens as $t) {
                        $w->orWhere('display', 'like', "%{$t}%");
                    }
                });
            })
            ->limit(80)
            ->get()
            ->map(fn($r) => (array)$r)
            ->all();

        // skor kasar: string similarity untuk re-rank sebelum ke LLM (hemat token)
        foreach ($query as &$row) {
            $row['score'] = $this->similarity($q, mb_strtolower($row['display'] ?? ''));
        }
        usort($query, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_values(array_slice($query, 0, 30)); // kirim maksimal 30 kandidat ke LLM
    }

    protected function similarity(string $a, string $b): float
    {
        // Jaro-Winkler sederhana pakai similar_text normalized
        similar_text($a, $b, $perc);
        return $perc / 100.0;
    }

    protected function askLlm(string $cleanText, array $candidates): array
    {
        // SCHEMA ketat: pilih salah satu dari candidates by CODE saja
        $sys = "You are a medical coding assistant mapping free-text allergy notes to SNOMED CT allergy substances.
- You MUST select exactly ONE entry from the provided candidates array.
- If multiple plausible, choose the highest-specific medication substance (not general class) that best matches.
- Always return strict JSON with keys: code, display, category, confidence (0..1), why (short).
- Never invent a code that is not in candidates.";

        // ringkas kandidat agar hemat token
        $candShort = array_map(fn($c) => [
            'code' => $c['code'],
            'display' => $c['display'],
            'category' => $c['category'] ?? 'medication',
        ], $candidates);

        $prompt = [
            ['role' => 'system', 'content' => $sys],
            [
                'role' => 'user',
                'content' => json_encode([
                    'allergy_text' => $cleanText,
                    'candidates'   => $candShort
                ], JSON_UNESCAPED_UNICODE)
            ],
        ];

        $resp = Http::withToken($this->openAiKey)
            ->timeout(20)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->openAiModel,
                'response_format' => ['type' => 'json_object'],
                'messages' => $prompt,
                'temperature' => 0.2,
                'max_tokens' => 300,
            ])->throw()->json();

        $raw = $resp['choices'][0]['message']['content'] ?? '{}';
        $json = json_decode($raw, true) ?: [];

        // validasi minimal
        $pick = [
            'code'       => $json['code']       ?? null,
            'display'    => $json['display']    ?? null,
            'category'   => $json['category']   ?? 'medication',
            'confidence' => max(0.0, min(1.0, (float)($json['confidence'] ?? 0.6))),
            'why'        => $json['why']        ?? null,
        ];

        // safety: wajib ada di kandidat
        $in = collect($candShort)->firstWhere('code', $pick['code']);
        if (!$in) {
            // fallback: ambil kandidat teratas
            $top = $candShort[0];
            return [
                'code' => $top['code'],
                'display' => $top['display'],
                'category' => $top['category'] ?? 'medication',
                'confidence' => 0.55,
                'why' => 'fallback-top-candidate',
            ];
        }

        return $pick;
    }
}

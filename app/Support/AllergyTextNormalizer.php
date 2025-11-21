<?php

namespace App\Support;

use Illuminate\Support\Str;
use App\Models\SnomedAllerged;

class AllergyTextNormalizer
{
    public static array $negationPatterns = [
        '/^\s*0+\s*$/i',
        '/^\s*=\s*$/',
        '/^\s*-\s*$/',
        '/^\s*8-?\s*$/',
        '/^\s*77\s*$/',
        '/^\s*80\s*$/',
        '/^\s*99\s*$/',
        '/t(idak)?\s*d(a)?\s*a/i',
        '/tidak\s+ada\s+alerg(i|y)/i',
        '/lupa/i',
        '/\?{2,}/',
        '/^p-$/i'
    ];

    public static array $synonymMap = [
        'ponstan' => 'mefenamic acid',
        'mefinal' => 'mefenamic acid',
        'asam mefenamat' => 'mefenamic acid',
        'asmef' => 'mefenamic acid',
        'antalgin' => 'metamizole',
        'analsik' => 'metamizole',
        'neuralgin' => 'metamizole',
        'mizole' => 'metamizole',
        'amoxcilin' => 'amoxicillin',
        'amoxicilin' => 'amoxicillin',
        'amoxcillin' => 'amoxicillin',
        'amoxillin' => 'amoxicillin',
        'amoxycillin' => 'amoxicillin',
        'amoxycilin' => 'amoxicillin',
        'amoxan' => 'amoxicillin',
        'ampicilin' => 'ampicillin',
        'penisilin' => 'penicillin',
        'pinicilin' => 'penicillin',
        'pinisilin' => 'penicillin',
        'penicilin' => 'penicillin',
        'ciproflaxacin' => 'ciprofloxacin',
        'cipro' => 'ciprofloxacin',
        'cloracef' => 'cefaclor',
        'ceftrioxon' => 'ceftriaxone',
        'ibuprof en' => 'ibuprofen',
        'ibu profen' => 'ibuprofen',
        'promag' => 'antacid',
        'obat maag' => 'antacid',
        'alopurinol' => 'allopurinol',
        'alupurinol' => 'allopurinol',
    ];

    public static function clean(string $s): string
    {
        $s = Str::of($s)->replace(["\n", "\r", "\t"], ' ')->lower();
        $s = preg_replace('/\s+/', ' ', (string) $s);
        return trim(preg_replace('/[\'"=]+/', ' ', $s));
    }

    public static function isNegation(string $s): bool
    {
        foreach (self::$negationPatterns as $re) if (preg_match($re, $s)) return true;
        return false;
    }

    public static function splitItems(string $s): array
    {
        $s = preg_replace('/\s+dan\s+/i', ',', $s);
        $s = preg_replace('/\(\s*\?\s*\)/', '', $s);
        $parts = preg_split('/[,;+\/]/', $s);
        return array_values(array_filter(array_map(fn($x) => trim($x, " -"), $parts)));
    }

    public static function normalizeToken(string $token): string
    {
        $t = self::clean($token);
        $t = preg_replace('/^(obat|jenis\s+obat|golongan)\s+/i', '', $t);
        if (isset(self::$synonymMap[$t])) return self::$synonymMap[$t];
        foreach (self::$synonymMap as $k => $v) if (str_contains($t, $k)) return $v;
        return $t;
    }

    public static function guessCategory(string $token): string
    {
        $t = self::clean($token);
        if (str_contains($t, 'udang') || str_contains($t, 'seafood') || str_contains($t, 'ikan') || str_contains($t, 'telur') || str_contains($t, 'makanan')) return 'food';
        if (str_contains($t, 'debu') || str_contains($t, 'dingin') || str_contains($t, 'cuaca')) return 'environment';
        return 'medication';
    }

    public static function findBest(string $normalized): ?array
    {
        if ($normalized === '' || in_array($normalized, ['antibiotic (class)', 'analgesic', 'antacid'], true)) return null;

        $q1 = SnomedAllerged::whereRaw('LOWER(display)=?', [strtolower($normalized)])->first();
        if ($q1) return $q1->toArray();

        $q2 = SnomedAllerged::where('display', 'LIKE', '%' . $normalized . '%')->first();
        if ($q2) return $q2->toArray();

        $q3 = SnomedAllerged::where('code', 'LIKE', '%' . $normalized . '%')->first();
        return $q3 ? $q3->toArray() : null;
    }

    /** return array of structured items from one free text line */
    public static function processLine(string $input): array
    {
        $clean = self::clean($input);
        if ($clean === '' || self::isNegation($clean)) return [];

        $tokens = self::splitItems($clean) ?: [$clean];
        $out = [];
        foreach ($tokens as $t) {
            $norm = self::normalizeToken($t);
            $cat  = self::guessCategory($norm);
            $best = self::findBest($norm);
            $out[] = [
                'raw'        => $t,
                'normalized' => $norm,
                'category'   => $cat,
                'code'       => $best['code'] ?? null,
                'display'    => $best['display'] ?? null,
                'matched'    => (bool)$best,
            ];
        }
        return $out;
    }
}

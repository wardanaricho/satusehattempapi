<?php

namespace Database\Seeders;

use App\Models\SnomedAllerged;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ImportSnomedAllergensSeeder extends Seeder
{
    public function run(): void
    {
        // Ubah path sesuai lokasi file kamu:
        $path = storage_path('app/imports/snomed_allergy_categories_strict.csv');
        if (! file_exists($path)) {
            throw new \RuntimeException("CSV not found at: {$path}");
        }

        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new \RuntimeException("Unable to open CSV: {$path}");
        }

        // Baca header
        $header = fgetcsv($handle);
        // Normalisasi nama kolom
        $header = array_map(fn($h) => strtolower(trim($h)), $header);

        // Ambil index kolom
        $iCode       = array_search('code', $header);
        $iDisplay    = array_search('display', $header);
        $iCodesystem = array_search('codesystem', $header);
        $iCategory   = array_search('category', $header);

        if (in_array(false, [$iCode, $iDisplay, $iCodesystem, $iCategory], true)) {
            throw new \RuntimeException('CSV header must include Code, Display, Codesystem, category');
        }

        DB::beginTransaction();
        try {
            $batch = [];
            $batchSize = 1000;

            while (($row = fgetcsv($handle)) !== false) {
                // Skip baris kosong
                if (count($row) < 4) continue;

                $code       = trim((string) $row[$iCode]);
                $display    = trim((string) $row[$iDisplay]);
                $codesystem = trim((string) $row[$iCodesystem]);
                $category   = trim((string) $row[$iCategory]);

                if ($code === '' || $display === '') continue;

                $batch[] = [
                    'code'       => $code,
                    'display'    => $display,
                    'codesystem' => $codesystem ?: 'http://snomed.info/sct',
                    'category'   => $category, // harus salah satu dari enum
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($batch) >= $batchSize) {
                    $this->upsertBatch($batch);
                    $batch = [];
                }
            }

            if ($batch) {
                $this->upsertBatch($batch);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($handle);
            throw $e;
        }

        fclose($handle);
    }

    protected function upsertBatch(array $batch): void
    {
        // Upsert by 'code'
        SnomedAllerged::query()->upsert(
            $batch,
            uniqueBy: ['code'],
            update: ['display', 'codesystem', 'category', 'updated_at']
        );
    }
}

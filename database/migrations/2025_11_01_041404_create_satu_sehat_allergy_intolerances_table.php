<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('satu_sehat_allergy_intolerance', function (Blueprint $table) {
            $table->uuid('id')->primary(); // "id" dari FHIR

            // Relasi lokal (opsional)
            $table->string('no_rawat', 17)->nullable()->index();

            // Referensi FHIR inti
            $table->string('encounter_reference')->nullable();     // Encounter/634f...
            $table->string('patient_reference')->nullable();       // Patient/P0115...
            $table->string('practitioner_reference')->nullable();  // Practitioner/100104...

            // Identifier resmi dari SatuSehat (jika ada)
            $table->string('identifier_system')->nullable();       // http://sys-ids.kemkes.go.id/allergy/...
            $table->string('identifier_value')->nullable();        // 100026972

            // Field minimal sesuai JSON
            $table->string('resource_type')->default('AllergyIntolerance');

            $table->string('category')->nullable();                // "medication" (ambil elemen pertama)
            $table->string('clinical_status')->nullable();         // "active"
            $table->string('verification_status')->nullable();     // "confirmed"

            // Kode alergen (SNOMED)
            $table->string('code_system')->nullable();             // http://snomed.info/sct
            $table->string('code_code')->nullable();               // 27658006
            $table->string('code_display')->nullable();            // AMOXILIN
            $table->string('code_text')->nullable();               // Alergi AMOXILIN

            // Tanggal
            $table->timestamp('recorded_date')->nullable();        // 2025-10-23T15:17:20+07:00
            $table->timestamp('meta_last_updated')->nullable();    // 2025-11-01T04:04:13...

            // Simpan payload penuh untuk audit/debug
            $table->json('response_json')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satu_sehat_allergy_intolerance');
    }
};

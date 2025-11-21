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
        Schema::create('satu_sehat_questionnaire_response', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // relasi ke reg_periksa
            $table->string('no_rawat', 17)->index();

            $table->string('questionnaire')->nullable();
            $table->string('encounter_reference')->nullable();
            $table->string('patient_reference')->nullable();
            $table->string('practitioner_reference')->nullable();

            $table->string('identifier_system')->nullable();
            $table->string('identifier_value')->nullable();

            $table->enum('status', ['completed', 'draft', 'error'])->default('completed');
            $table->timestamp('authored_at')->nullable();

            $table->json('response_json')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('satu_sehat_questionnaire_response');
    }
};

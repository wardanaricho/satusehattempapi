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
        Schema::create('snomed_allergeds', function (Blueprint $table) {
            $table->id();
            // Penting: SNOMED conceptId bisa > 32-bit, jadi simpan sebagai string
            $table->string('code', 30)->unique()->index();
            $table->text('display'); // nama/teks bisa panjang
            $table->string('codesystem', 100)->default('http://snomed.info/sct');
            $table->enum('category', ['food', 'medication', 'environment', 'biologic'])->index();
            $table->timestamps();

            // Optional: fulltext index untuk pencarian 'display' (MySQL 5.7+/8+)
            // $table->fullText('display');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('snomed_allergeds');
    }
};

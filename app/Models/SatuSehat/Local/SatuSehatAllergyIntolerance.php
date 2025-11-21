<?php

namespace App\Models\SatuSehat\Local;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SatuSehatAllergyIntolerance extends Model
{
    use HasUuids;

    protected $connection = 'mysql'; // ganti bila tabel ada di koneksi lain
    protected $table = 'satu_sehat_allergy_intolerance';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'no_rawat',
        'encounter_reference',
        'patient_reference',
        'practitioner_reference',
        'identifier_system',
        'identifier_value',
        'resource_type',
        'category',
        'clinical_status',
        'verification_status',
        'code_system',
        'code_code',
        'code_display',
        'code_text',
        'recorded_date',
        'meta_last_updated',
        'response_json',
    ];

    protected $casts = [
        'recorded_date'     => 'datetime',
        'meta_last_updated' => 'datetime',
        'response_json'     => 'array',
    ];
}

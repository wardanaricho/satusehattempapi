<?php

namespace App\Models\SatuSehat\Local;

use App\Models\RegPeriksa;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SatuSehatQuestionnaireResponse extends Model
{
    use HasUuids;

    protected $connection = 'mysql';
    protected $table = 'satu_sehat_questionnaire_response';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'no_rawat',
        'questionnaire',
        'encounter_reference',
        'patient_reference',
        'practitioner_reference',
        'identifier_system',
        'identifier_value',
        'status',
        'authored_at',
        'response_json',
    ];

    protected $casts = [
        'response_json' => 'array',
        'authored_at' => 'datetime',
    ];

    public function regPeriksa()
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }
}

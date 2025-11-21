<?php

namespace App\Models;

use App\Models\SatuSehat\Local\SatuSehatAllergyIntolerance;
use App\Models\SatuSehat\Local\SatuSehatQuestionnaireResponse;
use App\Models\SatuSehat\SatuSehatEncounter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegPeriksa extends Model
{
    protected $connection = 'mysql_2';
    protected $table = 'reg_periksa';
    protected $primaryKey = 'no_rawat';
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';

    public function satuSehatEncounter()
    {
        return $this->hasOne(SatuSehatEncounter::class, 'no_rawat', 'no_rawat');
    }

    public function pemeriksaanRalan(): HasMany
    {
        return $this->hasMany(PemeriksaanRalan::class, 'no_rawat', 'no_rawat');
    }

    public function pasien()
    {
        return $this->belongsTo(Pasien::class, 'no_rkm_medis', 'no_rkm_medis');
    }

    public function dokter(): BelongsTo
    {
        return $this->belongsTo(Dokter::class, 'kd_dokter', 'kd_dokter');
    }

    public function questionnaireResponse()
    {
        return $this->hasOne(SatuSehatQuestionnaireResponse::class, 'no_rawat', 'no_rawat');
    }

    public function allergyIntolerance()
    {
        return $this->hasOne(SatuSehatAllergyIntolerance::class, 'no_rawat', 'no_rawat');
    }
}

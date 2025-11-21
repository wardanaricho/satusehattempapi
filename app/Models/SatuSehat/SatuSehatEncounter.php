<?php

namespace App\Models\SatuSehat;

use App\Models\RegPeriksa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SatuSehatEncounter extends Model
{
    protected $connection = 'mysql_2';
    protected $table = 'satu_sehat_encounter';
    protected $primaryKey = 'no_rawat';
    public $incrementing = false;
    public $timestamps = false;
    public $keyType = 'string';

    public function regPeriksa(): BelongsTo
    {
        return $this->belongsTo(RegPeriksa::class, ' no_rawat', 'no_rawat');
    }
}

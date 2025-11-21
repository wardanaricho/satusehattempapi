<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PemeriksaanRalan extends Model
{
    protected $connection = 'mysql_2';
    protected $table = 'pemeriksaan_ralan';
    protected $primaryKey = 'no_rawat';
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';

    /**
     * Get the regPeriksa that owns the PemeriksaanRalan
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function regPeriksa(): BelongsTo
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dokter extends Model
{
    protected $connection = 'mysql_2';
    protected $table = 'dokter';
    protected $primaryKey = 'kd_dokter';
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';

    /**
     * Get the pegawai that owns the Dokter
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'kd_dokter', 'nik');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pegawai extends Model
{
    protected $connection = 'mysql_2';
    protected $table = 'pegawai';
    protected $primaryKey = 'nik';
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';
}

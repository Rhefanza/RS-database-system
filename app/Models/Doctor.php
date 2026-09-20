<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Doctor extends Model
{
    protected $table = 'dokter';

    protected $primaryKey = 'dokter_id';

    protected $fillable = ['puskesmas_layanan_id', 'nama_dokter', 'spesialisasi', 'status'];

    public function puskesmasService(): BelongsTo
    {
        return $this->belongsTo(PuskesmasService::class, 'puskesmas_layanan_id', 'puskesmas_layanan_id');
    }
}

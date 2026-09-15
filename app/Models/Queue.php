<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Queue extends Model
{
    protected $table = 'antrean';

    protected $primaryKey = 'antrean_id';

    protected $fillable = ['nik', 'jadwal_id', 'nomor_antrean', 'tanggal_daftar', 'status_antrean'];

    protected function casts(): array
    {
        return ['nomor_antrean' => 'integer', 'tanggal_daftar' => 'date'];
    }

    public function citizen(): BelongsTo
    {
        return $this->belongsTo(Citizen::class, 'nik', 'nik');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'jadwal_id', 'jadwal_id');
    }
}

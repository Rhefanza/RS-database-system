<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Schedule extends Model
{
    protected $table = 'jadwal';

    protected $primaryKey = 'jadwal_id';

    protected $fillable = ['puskesmas_id', 'layanan_id', 'nama_dokter', 'spesialisasi', 'hari', 'jam_buka', 'jam_tutup', 'kapasitas', 'status'];

    protected function casts(): array
    {
        return ['kapasitas' => 'integer'];
    }

    public function puskesmas(): BelongsTo
    {
        return $this->belongsTo(Puskesmas::class, 'puskesmas_id', 'puskesmas_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'layanan_id', 'layanan_id');
    }

    public function queues(): HasMany
    {
        return $this->hasMany(Queue::class, 'jadwal_id', 'jadwal_id');
    }
}

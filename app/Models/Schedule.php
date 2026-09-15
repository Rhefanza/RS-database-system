<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Schedule extends Model
{
    protected $table = 'jadwal';

    protected $primaryKey = 'jadwal_id';

    protected $fillable = ['puskesmas_layanan_id', 'hari', 'jam_buka', 'jam_tutup', 'kapasitas', 'status'];

    protected function casts(): array
    {
        return ['kapasitas' => 'integer'];
    }

    public function puskesmasService(): BelongsTo
    {
        return $this->belongsTo(PuskesmasService::class, 'puskesmas_layanan_id', 'puskesmas_layanan_id');
    }

    public function queues(): HasMany
    {
        return $this->hasMany(Queue::class, 'jadwal_id', 'jadwal_id');
    }
}

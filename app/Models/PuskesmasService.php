<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PuskesmasService extends Model
{
    protected $table = 'puskesmas_layanan';

    protected $primaryKey = 'puskesmas_layanan_id';

    protected $fillable = ['puskesmas_id', 'layanan_id', 'status'];

    public function puskesmas(): BelongsTo
    {
        return $this->belongsTo(Puskesmas::class, 'puskesmas_id', 'puskesmas_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'layanan_id', 'layanan_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'puskesmas_layanan_id', 'puskesmas_layanan_id');
    }

    public function doctors(): HasMany
    {
        return $this->hasMany(Doctor::class, 'puskesmas_layanan_id', 'puskesmas_layanan_id');
    }
}

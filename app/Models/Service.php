<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $table = 'layanan';

    protected $primaryKey = 'layanan_id';

    protected $fillable = ['nama_layanan', 'deskripsi', 'status'];

    public function puskesmas(): BelongsToMany
    {
        return $this->belongsToMany(Puskesmas::class, 'puskesmas_layanan', 'layanan_id', 'puskesmas_id')
            ->withPivot(['puskesmas_layanan_id', 'status'])->withTimestamps();
    }

    public function puskesmasServices(): HasMany
    {
        return $this->hasMany(PuskesmasService::class, 'layanan_id', 'layanan_id');
    }
}

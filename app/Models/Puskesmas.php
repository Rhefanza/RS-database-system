<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Puskesmas extends Model
{
    use HasFactory;

    protected $table = 'puskesmas';

    protected $primaryKey = 'puskesmas_id';

    protected $fillable = ['kecamatan_id', 'nama_puskesmas', 'alamat', 'latitude', 'longitude', 'nomor_telepon', 'status'];

    protected function casts(): array
    {
        return ['latitude' => 'decimal:7', 'longitude' => 'decimal:7'];
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'puskesmas_layanan', 'puskesmas_id', 'layanan_id')
            ->withPivot(['puskesmas_layanan_id', 'status'])->withTimestamps();
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'kecamatan_id', 'kecamatan_id');
    }

    public function puskesmasServices(): HasMany
    {
        return $this->hasMany(PuskesmasService::class, 'puskesmas_id', 'puskesmas_id');
    }

    public function officers(): HasMany
    {
        return $this->hasMany(User::class, 'puskesmas_id', 'puskesmas_id')->where('role', 'PETUGAS');
    }
}

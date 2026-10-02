<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $table = 'layanan';

    protected $primaryKey = 'layanan_id';

    protected $fillable = ['nama_layanan', 'deskripsi', 'status'];


    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'layanan_id', 'layanan_id');
    }
}

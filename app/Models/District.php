<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    protected $table = 'kecamatan';

    protected $primaryKey = 'kecamatan_id';

    protected $fillable = ['nama_kecamatan'];

    public function puskesmas(): HasMany
    {
        return $this->hasMany(Puskesmas::class, 'kecamatan_id', 'kecamatan_id');
    }
}

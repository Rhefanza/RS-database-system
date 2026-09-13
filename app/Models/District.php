<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    protected $fillable = ['name'];

    public function hospitals(): HasMany
    {
        return $this->hasMany(Hospital::class);
    }
}

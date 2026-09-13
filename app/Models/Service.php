<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = ['name', 'description'];

    public function hospitals(): BelongsToMany
    {
        return $this->belongsToMany(Hospital::class, 'hospital_services')
            ->withPivot(['id', 'initial_service_duration', 'availability_status'])
            ->withTimestamps();
    }

    public function hospitalServices(): HasMany
    {
        return $this->hasMany(HospitalService::class);
    }
}

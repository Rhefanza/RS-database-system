<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceDesk extends Model
{
    protected $fillable = ['hospital_service_id', 'name', 'desk_status'];

    public function hospitalService(): BelongsTo
    {
        return $this->belongsTo(HospitalService::class);
    }

    public function queues(): HasMany
    {
        return $this->hasMany(Queue::class);
    }
}

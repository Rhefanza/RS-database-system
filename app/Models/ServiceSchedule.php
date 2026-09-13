<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceSchedule extends Model
{
    protected $fillable = ['hospital_service_id', 'day', 'opens_at', 'closes_at', 'quota', 'schedule_status'];

    protected function casts(): array
    {
        return ['quota' => 'integer'];
    }

    public function hospitalService(): BelongsTo
    {
        return $this->belongsTo(HospitalService::class);
    }
}

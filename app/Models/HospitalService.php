<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HospitalService extends Model
{
    protected $fillable = ['hospital_id', 'service_id', 'initial_service_duration', 'availability_status'];

    protected function casts(): array
    {
        return ['initial_service_duration' => 'integer'];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ServiceSchedule::class);
    }

    public function specialSchedules(): HasMany
    {
        return $this->hasMany(SpecialServiceSchedule::class);
    }

    public function desks(): HasMany
    {
        return $this->hasMany(ServiceDesk::class);
    }

    public function queueSessions(): HasMany
    {
        return $this->hasMany(QueueSession::class);
    }

    public function queueSnapshots(): HasMany
    {
        return $this->hasMany(QueueSnapshot::class);
    }
}

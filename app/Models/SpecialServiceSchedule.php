<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecialServiceSchedule extends Model
{
    protected $fillable = ['hospital_service_id', 'date', 'special_opens_at', 'special_closes_at', 'status', 'reason'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function hospitalService(): BelongsTo
    {
        return $this->belongsTo(HospitalService::class);
    }
}

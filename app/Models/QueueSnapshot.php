<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueSnapshot extends Model
{
    protected $fillable = ['hospital_service_id', 'captured_at', 'waiting_count', 'serving_count', 'completed_count', 'active_desk_count', 'estimated_wait_minutes'];

    protected function casts(): array
    {
        return ['captured_at' => 'datetime', 'waiting_count' => 'integer', 'serving_count' => 'integer', 'completed_count' => 'integer', 'active_desk_count' => 'integer', 'estimated_wait_minutes' => 'integer'];
    }

    public function hospitalService(): BelongsTo
    {
        return $this->belongsTo(HospitalService::class);
    }
}

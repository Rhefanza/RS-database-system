<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Queue extends Model
{
    protected $fillable = ['queue_session_id', 'queue_number', 'service_desk_id', 'queue_status', 'called_at', 'service_started_at', 'service_ended_at'];

    protected function casts(): array
    {
        return ['queue_number' => 'integer', 'called_at' => 'datetime', 'service_started_at' => 'datetime', 'service_ended_at' => 'datetime'];
    }

    public function queueSession(): BelongsTo
    {
        return $this->belongsTo(QueueSession::class);
    }

    public function serviceDesk(): BelongsTo
    {
        return $this->belongsTo(ServiceDesk::class);
    }
}

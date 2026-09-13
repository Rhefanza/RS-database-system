<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QueueSession extends Model
{
    protected $fillable = ['hospital_service_id', 'opened_by_user_id', 'closed_by_user_id', 'session_date', 'started_at', 'ended_at', 'session_status'];

    protected function casts(): array
    {
        return ['session_date' => 'date', 'started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function hospitalService(): BelongsTo
    {
        return $this->belongsTo(HospitalService::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function queues(): HasMany
    {
        return $this->hasMany(Queue::class);
    }
}

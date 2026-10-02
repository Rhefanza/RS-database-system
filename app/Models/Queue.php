<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Queue extends Model
{
    public const ACTIVE_STATUSES = ['WAITING', 'CALLED', 'SERVING'];

    protected $table = 'antrean';

    protected $primaryKey = 'antrean_id';

    protected $fillable = ['akun_id', 'jadwal_id', 'nomor_antrean', 'tanggal_daftar', 'status_antrean'];

    protected function casts(): array
    {
        return ['nomor_antrean' => 'integer', 'tanggal_daftar' => 'date'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status_antrean', self::ACTIVE_STATUSES);
    }

    public static function overlappingActiveFor(int $accountId, Schedule $schedule, string $date): ?self
    {
        return self::query()
            ->active()
            ->where('akun_id', $accountId)
            ->whereDate('tanggal_daftar', $date)
            ->whereHas('schedule', fn (Builder $query) => $query
                ->where('jam_buka', '<', $schedule->jam_tutup)
                ->where('jam_tutup', '>', $schedule->jam_buka))
            ->with('schedule.puskesmas', 'schedule.service')
            ->first();
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(User::class, 'akun_id', 'akun_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'jadwal_id', 'jadwal_id');
    }
}

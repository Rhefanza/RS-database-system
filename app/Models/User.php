<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'akun';

    protected $primaryKey = 'akun_id';

    protected $fillable = ['nik', 'puskesmas_id', 'nama_lengkap', 'email', 'password_hash', 'role', 'status_akun', 'nomor_telepon', 'alamat', 'status_data'];

    protected $attributes = ['status_data' => 'AKTIF'];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return ['password_hash' => 'hashed'];
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function getAuthPassword(): string
    {
        return $this->password_hash ?? '';
    }

    public function scopeCitizens(Builder $query): Builder
    {
        return $query->where('role', 'MASYARAKAT');
    }

    public function queues(): HasMany
    {
        return $this->hasMany(Queue::class, 'akun_id', 'akun_id');
    }

    public function puskesmas(): BelongsTo
    {
        return $this->belongsTo(Puskesmas::class, 'puskesmas_id', 'puskesmas_id');
    }
}

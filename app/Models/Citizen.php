<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Citizen extends Model
{
    protected $table = 'masyarakat';

    protected $primaryKey = 'nik';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['nik', 'nama_lengkap', 'nomor_telepon', 'alamat', 'status_data'];

    public function account(): HasOne
    {
        return $this->hasOne(User::class, 'nik', 'nik');
    }

    public function queues(): HasMany
    {
        return $this->hasMany(Queue::class, 'nik', 'nik');
    }
}

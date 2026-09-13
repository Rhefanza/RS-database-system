<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hospital extends Model
{
    use HasFactory;

    protected $fillable = [
        'district_id',
        'name',
        'code',
        'class',
        'ownership',
        'phone',
        'emergency_phone',
        'address',
        'city',
        'latitude',
        'longitude',
        'description',
        'is_emergency',
        'data_status',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_emergency' => 'boolean',
        ];
    }

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'hospital_facilities');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'hospital_services')
            ->withPivot(['id', 'initial_service_duration', 'availability_status'])
            ->withTimestamps();
    }

    public function hospitalServices(): HasMany
    {
        return $this->hasMany(HospitalService::class);
    }

    public function staffAssignments(): HasMany
    {
        return $this->hasMany(StaffAssignment::class);
    }
}

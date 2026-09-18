<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $coordinates = [
        'Puskesmas Ketabang' => [-7.2567000, 112.7505000],
        'Puskesmas Mulyorejo' => [-7.2676000, 112.7985000],
        'Puskesmas Jagir' => [-7.3066000, 112.7444000],
    ];

    public function up(): void
    {
        foreach ($this->coordinates as $name => [$latitude, $longitude]) {
            DB::table('puskesmas')
                ->where('nama_puskesmas', $name)
                ->whereNull('latitude')
                ->whereNull('longitude')
                ->update(['latitude' => $latitude, 'longitude' => $longitude]);
        }
    }

    public function down(): void
    {
        foreach ($this->coordinates as $name => [$latitude, $longitude]) {
            DB::table('puskesmas')
                ->where('nama_puskesmas', $name)
                ->where('latitude', $latitude)
                ->where('longitude', $longitude)
                ->update(['latitude' => null, 'longitude' => null]);
        }
    }
};

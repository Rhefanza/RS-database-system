<?php

namespace Database\Factories;

use App\Models\District;
use App\Models\Puskesmas;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Puskesmas> */
class PuskesmasFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kecamatan_id' => District::firstOrCreate(['nama_kecamatan' => 'Kecamatan Uji'])->kecamatan_id,
            'nama_puskesmas' => 'Puskesmas '.$this->faker->unique()->city(),
            'alamat' => $this->faker->streetAddress(),
            'nomor_telepon' => $this->faker->phoneNumber(),
            'status' => 'AKTIF',
        ];
    }
}

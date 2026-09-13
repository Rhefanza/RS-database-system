<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class HospitalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'RS '.$this->faker->unique()->company(),
            'code' => 'RS-'.$this->faker->unique()->numerify('####'),
            'class' => $this->faker->randomElement(['A', 'B', 'C', 'D']),
            'ownership' => $this->faker->randomElement(['Pemerintah', 'BUMN', 'Swasta']),
            'phone' => $this->faker->phoneNumber(),
            'emergency_phone' => $this->faker->optional()->phoneNumber(),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'latitude' => $this->faker->latitude(-8.0, -6.5),
            'longitude' => $this->faker->longitude(110.0, 114.0),
            'description' => $this->faker->sentence(),
            'is_emergency' => $this->faker->boolean(),
        ];
    }
}

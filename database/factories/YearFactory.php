<?php

namespace Database\Factories;

use App\Models\Year;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Year> */
class YearFactory extends Factory
{
    protected $model = Year::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['1º Ano', '2º Ano', '3º Ano', '4º Ano']),
        ];
    }
}

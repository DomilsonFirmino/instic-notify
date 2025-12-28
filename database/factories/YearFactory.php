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
        $anos = ['1', '2', '3', '4', '5'];
        return [
            'name' => $this->faker->randomElement($anos),
        ];
    }
}

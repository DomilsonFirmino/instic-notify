<?php

namespace Database\Factories;

use App\Models\Log;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Log> */
class LogFactory extends Factory
{
    protected $model = Log::class;

    public function definition(): array
    {
        return [
            'user_id' => function () {
                $user = \App\Models\User::inRandomOrder()->first();
                return $user ? $user->id : \App\Models\User::factory()->create()->id;
            },
            'action' => $this->faker->randomElement(['create','update','publish','reject','delete']),
            'description' => $this->faker->sentence(),
            'created_at' => now(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Favorite;
use App\Models\Informativo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Favorite> */
class FavoriteFactory extends Factory
{
    protected $model = Favorite::class;

    public function definition(): array
    {
        return [
            'user_id' => function () {
                $user = \App\Models\User::inRandomOrder()->first();
                return $user ? $user->id : \App\Models\User::factory()->create()->id;
            },
            'informativo_id' => function () {
                $inf = \App\Models\Informativo::inRandomOrder()->first();
                return $inf ? $inf->id : \App\Models\Informativo::factory()->create()->id;
            },
            'created_at' => now(),
        ];
    }
}

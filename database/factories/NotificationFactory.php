<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Notification> */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        $read = $this->faker->boolean(50);
        return [
            'user_id' => function () {
                $user = \App\Models\User::inRandomOrder()->first();
                return $user ? $user->id : \App\Models\User::factory()->create()->id;
            },
            'title' => $this->faker->sentence(4),
            'message' => $this->faker->paragraph(2, true),
            'read_at' => $read ? now() : null,
            'created_at' => now(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Department;
use App\Models\Year;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => fake()->randomElement(['admin','editor','revisor','leitor']),
            'course_id' => function () {
                $course = \App\Models\Course::inRandomOrder()->first();
                if ($course) return $course->id;
                // ensure courses exist
                foreach (['Engenharia Informatica','Telecomunicações','Informatica de Gestão'] as $name) {
                    \App\Models\Course::firstOrCreate(['name' => $name], ['department_id' => \App\Models\Department::firstOrCreate(['name' => $name])->id]);
                }
                return \App\Models\Course::inRandomOrder()->first()->id;
            },
            'year_id' => function () {
                $year = \App\Models\Year::inRandomOrder()->first();
                if ($year) return $year->id;
                foreach (['1','2','3','4','5'] as $n) { \App\Models\Year::firstOrCreate(['name' => $n]); }
                return \App\Models\Year::inRandomOrder()->first()->id;
            },
            'department_id' => function () {
                $dep = \App\Models\Department::inRandomOrder()->first();
                if ($dep) return $dep->id;
                foreach (['Engenharia Informatica','Telecomunicações','Informatica de Gestão'] as $name) { \App\Models\Department::firstOrCreate(['name' => $name]); }
                return \App\Models\Department::inRandomOrder()->first()->id;
            },
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}

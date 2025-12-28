<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Course;
use App\Models\Informativo;
use App\Models\User;
use App\Models\Year;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Informativo> */
class InformativoFactory extends Factory
{
    protected $model = Informativo::class;

    public function definition(): array
    {
        $status = $this->faker->randomElement(['rascunho','revisao','publicado','rejeitado']);
        return [
            'title' => $this->faker->sentence(6),
            'content' => $this->faker->paragraphs(3, true),
            'status' => $status,
            'category_id' => function () {
                $cat = Category::inRandomOrder()->first();
                return $cat ? $cat->id : Category::factory()->create()->id;
            },
            'course_id' => function () {
                $course = Course::inRandomOrder()->first();
                return $course ? $course->id : Course::whereIn('name', ['Engenharia Informatica','Telecomunicações','Informatica de Gestão'])->inRandomOrder()->first()->id;
            },
            'year_id' => function () {
                $year = Year::inRandomOrder()->first();
                if ($year) return $year->id;
                // If no year exists, ensure we create the 5 allowed years first
                foreach (['1','2','3','4','5'] as $n) {
                    Year::firstOrCreate(['name' => $n]);
                }
                return Year::inRandomOrder()->first()->id;
            },
            'author_id' => function () {
                $user = User::inRandomOrder()->first();
                return $user ? $user->id : User::factory()->create()->id;
            },
            'published_by' => function () use ($status) {
                if ($status !== 'publicado') return null;
                $user = User::inRandomOrder()->first();
                return $user ? $user->id : User::factory()->create()->id;
            },
            'published_at' => $status === 'publicado' ? now() : null,
            'rejection_reason' => $status === 'rejeitado' ? $this->faker->sentence() : null,
        ];
    }
}

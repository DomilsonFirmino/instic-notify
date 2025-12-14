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
            'category_id' => Category::factory(),
            'course_id' => $this->faker->boolean(60) ? Course::factory() : null,
            'year_id' => $this->faker->boolean(60) ? Year::factory() : null,
            'author_id' => User::factory(),
            'published_by' => $status === 'publicado' ? User::factory() : null,
            'published_at' => $status === 'publicado' ? now() : null,
            'rejection_reason' => $status === 'rejeitado' ? $this->faker->sentence() : null,
        ];
    }
}

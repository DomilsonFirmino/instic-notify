<?php

namespace Database\Factories;

use App\Models\Informativo;
use App\Models\InformativoFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InformativoFile> */
class InformativoFileFactory extends Factory
{
    protected $model = InformativoFile::class;

    public function definition(): array
    {
        return [
            'informativo_id' => function () {
                $inf = \App\Models\Informativo::inRandomOrder()->first();
                return $inf ? $inf->id : \App\Models\Informativo::factory()->create()->id;
            },
            'path' => 'uploads/'.$this->faker->uuid().'.pdf',
            'original_name' => $this->faker->words(2, true).'.pdf',
            'size' => (string) $this->faker->numberBetween(10, 500).'KB',
        ];
    }
}

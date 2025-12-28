<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Department> */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        $names = [
            'Engenharia Informatica',
            'Telecomunicações',
            'Informatica de Gestão',
        ];
        return [
            'name' => $this->faker->unique()->randomElement($names),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Course> */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        $names = [
            'Engenharia Informatica',
            'Telecomunicações',
            'Informatica de Gestão',
        ];
        $name = $this->faker->randomElement($names);
        $department = Department::where('name', $name)->first();
        return [
            'name' => $name,
            'department_id' => $department ? $department->id : null,
        ];

    }
}

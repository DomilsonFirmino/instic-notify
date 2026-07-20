<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCourseRequest;
use App\Http\Requests\Api\V1\UpdateCourseRequest;
use App\Models\Course;
use App\Traits\ApiResponseTrait;

class CourseController extends Controller
{
    use ApiResponseTrait;

    public function index() {
        $courses = Course::paginate();
        $meta = [
            'current_page' => $courses->currentPage(),
            'per_page' => $courses->perPage(),
            'total' => $courses->total(),
            'last_page' => $courses->lastPage(),
        ];
        return $this->success($courses->items(), $meta, 200);
    }
    public function store(StoreCourseRequest $request) { $course = Course::create($request->validated()); return $this->success($course, status:201); }
    public function show(Course $course) { $course->load('department'); return $this->success($course); }
    public function update(UpdateCourseRequest $request, Course $course) { $course->update($request->validated()); return $this->success($course); }
    public function destroy(Course $course) { $course->delete(); return $this->success(['deleted'=>true]); }
}

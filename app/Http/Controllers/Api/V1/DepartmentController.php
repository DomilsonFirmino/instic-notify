<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Api\V1\StoreDepartmentRequest;
use App\Http\Requests\Api\V1\UpdateDepartmentRequest;
use App\Models\Department;

class DepartmentController extends ApiController
{

    public function index() { return $this->success(Department::query()->paginate()); }
    public function store(StoreDepartmentRequest $request)
    {
        $dep = Department::create($request->validated());
        return $this->success($dep, status:201);
    }
    public function show(Department $department) { return $this->success($department); }
    public function update(UpdateDepartmentRequest $request, Department $department) { $department->update($request->validated()); return $this->success($department); }
    public function destroy(Department $department) { $department->delete(); return $this->success(['deleted'=>true]); }
}

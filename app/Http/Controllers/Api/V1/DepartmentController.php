<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Api\V1\StoreDepartmentRequest;
use App\Http\Requests\Api\V1\UpdateDepartmentRequest;
use App\Models\Department;

class DepartmentController extends ApiController
{

    public function index() {
        $departments = Department::paginate();
        $meta = [
            'current_page' => $departments->currentPage(),
            'per_page' => $departments->perPage(),
            'total' => $departments->total(),
            'last_page' => $departments->lastPage(),
        ];
        return $this->success($departments->items(),$meta,200);
    }
    public function store(StoreDepartmentRequest $request)
    {
        $dep = Department::create($request->validated());
        return $this->success($dep, status:201);
    }
    public function show(Department $department) { return $this->success($department); }
    public function update(UpdateDepartmentRequest $request, Department $department) { $department->update($request->validated()); return $this->success($department); }
    public function destroy(Department $department) { $department->delete(); return $this->success(['deleted'=>true]); }
}

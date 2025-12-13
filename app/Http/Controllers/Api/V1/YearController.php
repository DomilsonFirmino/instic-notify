<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreYearRequest;
use App\Http\Requests\Api\V1\UpdateYearRequest;
use App\Models\Year;
use App\Traits\ApiResponseTrait;

class YearController extends Controller
{
    use ApiResponseTrait;

    public function index() { return $this->success(Year::query()->paginate()); }
    public function store(StoreYearRequest $request) { $year = Year::create($request->validated()); return $this->success($year, status:201); }
    public function show(Year $year) { return $this->success($year); }
    public function update(UpdateYearRequest $request, Year $year) { $year->update($request->validated()); return $this->success($year); }
    public function destroy(Year $year) { $year->delete(); return $this->success(['deleted'=>true]); }
}

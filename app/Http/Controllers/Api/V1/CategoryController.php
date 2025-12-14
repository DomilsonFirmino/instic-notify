<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Api\V1\StoreCategoryRequest;
use App\Http\Requests\Api\V1\UpdateCategoryRequest;
use App\Models\Category;

class CategoryController extends ApiController
{
    public function index()
    {
        return $this->success(Category::query()->paginate());
    }

    public function store(StoreCategoryRequest $request)
    {
        $category = Category::create($request->validated());
        return $this->success($category, [],201);
    }

    public function show(Category $category)
    {
        return $this->success($category,[],200);
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $category->update($request->validated());
        return $this->success($category);
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return $this->success(['deleted' => true]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = Category::query()
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($request->status !== null, function ($query) use ($request) {
                $query->where('status', $request->boolean('status'));
            })
            ->when($request->parent_id, function ($query, $parentId) {
                // Asegurarse de que parent_id sea tratado como UUID
                $query->where('parent_id', $parentId);
            })
            ->when($request->parent_id === null && $request->has('parent_id'), function ($query) {
                $query->whereNull('parent_id');
            })
            ->with('children')
            ->paginate($request->per_page ?? 15);

        return CategoryResource::collection($categories);
    }

    public function store(CategoryRequest $request)
    {
        $category = Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'parent_id' => $request->parent_id,
            'status' => $request->status ?? true
        ]);

        return new CategoryResource($category);
    }

    public function show(Category $category)
    {
        return new CategoryResource($category->load(['parent', 'children']));
    }

    public function update(CategoryRequest $request, Category $category)
    {
        $category->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'parent_id' => $request->parent_id,
            'status' => $request->status ?? $category->status
        ]);

        return new CategoryResource($category);
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return response()->json(null, 204);
    }
}

<?php
namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BlogController extends Controller
{
    public function __construct(
        protected BlogService $blogService
    ) {}

    public function index(Request $request)
    {
        $blogs = $this->blogService->listBlogs([
            'search' => $request->search,
            'status' => $request->status,
            'product_id' => $request->product_id,
            'per_page' => $request->per_page
        ]);

        return BlogResource::collection($blogs);
    }

    public function store(BlogRequest $request)
    {
        $blog = $this->blogService->createBlog(
            BlogData::fromRequest($request)
        );

        return new BlogResource($blog);
    }

    public function show(string $id)
    {
        $blog = $this->blogService->getBlog($id);
        
        return new BlogResource($blog);
    }

    public function update(BlogRequest $request, string $id)
    {
        $blog = $this->blogService->updateBlog(
            $id,
            BlogData::fromRequest($request)
        );

        return new BlogResource($blog);
    }

    public function destroy(string $id)
    {
        $this->blogService->deleteBlog($id);
        
        return response()->noContent();
    }
}

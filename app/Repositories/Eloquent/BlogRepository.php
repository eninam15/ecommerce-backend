<?php
namespace App\Repositories\Eloquent;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BlogRepository implements BlogRepositoryInterface
{
    public function __construct(protected Blog $model) {}

    public function findById(string $id): ?Blog
    {
        return $this->model->with(['products'])->find($id);
    }

    public function create(BlogData $data): Blog
    {
        $blog = $this->model->create([
            'title' => $data->title,
            'content' => $data->content,
            'status' => $data->status,
            'slug' => $data->slug,
            'created_by' => auth()->id(),
        ]);

        $blog->products()->attach($data->productIds);
        
        return $blog->load('products');
    }

    public function update(string $id, BlogData $data): ?Blog
    {
        $blog = $this->findById($id);
        if (!$blog) return null;

        $blog->update([
            'title' => $data->title,
            'content' => $data->content,
            'status' => $data->status,
            'slug' => $data->slug,
            'updated_by' => auth()->id(),
        ]);

        $blog->products()->sync($data->productIds);

        return $blog->load('products');
    }

    public function findByProduct(string $productId): Collection
    {
        return $this->model->whereHas('products', function ($query) use ($productId) {
            $query->where('products.id', $productId);
        })->get();
    }

    public function findByCriteria(array $criteria): LengthAwarePaginator
    {
        $query = $this->model->query();

        if (isset($criteria['search'])) {
            $query->where(function ($q) use ($criteria) {
                $q->where('title', 'like', "%{$criteria['search']}%")
                    ->orWhere('content', 'like', "%{$criteria['search']}%");
            });
        }

        if (isset($criteria['status'])) {
            $query->where('status', $criteria['status']);
        }

        if (isset($criteria['product_id'])) {
            $query->whereHas('products', function ($q) use ($criteria) {
                $q->where('products.id', $criteria['product_id']);
            });
        }

        return $query->with('products')->paginate($criteria['per_page'] ?? 15);
    }

    public function delete(string $id): bool
    {
        return $this->model->findOrFail($id)->delete();
    }
}

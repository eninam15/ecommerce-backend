<?php
namespace App\Repositories\Eloquent;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Repositories\Interfaces\PromotionRepositoryInterface;
use App\Dtos\PromotionData;
use App\Models\Promotion;


class PromotionRepository implements PromotionRepositoryInterface
{
    public function __construct(protected Promotion $promotion) {}

    public function findById(string $id)
    {
        return $this->promotion->with(['products'])->find($id);
    }

    public function create(PromotionData $data)
    {

        //dd("Aqui la informaicon de data: ", $data);

        $promotion = $this->promotion->create([
            'name' => $data->name,
            'description' => $data->description,
            'type' => $data->type->value,
            'discount_type' => $data->discountType->value,
            'discount_value' => $data->discountValue,
            'starts_at' => $data->startsAt,
            'ends_at' => $data->endsAt,
            'status' => $data->status,
            'min_quantity' => $data->minQuantity,
            'max_quantity' => $data->maxQuantity,
            'created_by' => "9e11891b-a9f8-475a-85ab-8061ae9dd73e",
        ]);

        foreach ($data->products as $product) {
            $promotion->products()->attach($product['product_id'], [
                'discount_value' => $product['discount_value'] ?? $data->discountValue,
                'quantity_required' => $product['quantity_required'] ?? 1
            ]);
        }

        return $promotion->load('products');
    }

    public function update(string $id, PromotionData $data)
    {
        $promotion = $this->findById($id);
        if (!$promotion) return null;

        $promotion->update([
            'name' => $data->name,
            'description' => $data->description,
            'type' => $data->type->value,
            'discount_type' => $data->discountType->value,
            'discount_value' => $data->discountValue,
            'starts_at' => $data->startsAt,
            'ends_at' => $data->endsAt,
            'status' => $data->status,
            'min_quantity' => $data->minQuantity,
            'max_quantity' => $data->maxQuantity,
            'updated_by' => auth()->id(),
        ]);

        // Sincronizar productos
        $productsData = collect($data->products)->mapWithKeys(function ($product) use ($data) {
            return [$product['product_id'] => [
                'discount_value' => $product['discount_value'] ?? $data->discountValue,
                'quantity_required' => $product['quantity_required'] ?? 1
            ]];
        })->all();

        $promotion->products()->sync($productsData);

        return $promotion->load('products');
    }

    public function delete(string $id)
    {
        return $this->promotion->findOrFail($id)->delete();
    }

    public function findActive()
    {
        return $this->promotion->active()
            ->with('products')
            ->get();
    }

    public function findByProduct(string $productId)
    {
        return $this->promotion->whereHas('products', function ($query) use ($productId) {
            $query->where('products.id', $productId);
        })->active()->get();
    }

    public function findByCriteria(array $criteria)
    {
        $query = $this->promotion->query();

        if (isset($criteria['search'])) {
            $query->where(function ($q) use ($criteria) {
                $q->where('name', 'like', "%{$criteria['search']}%")
                    ->orWhere('description', 'like', "%{$criteria['search']}%");
            });
        }

        if (isset($criteria['status'])) {
            $query->where('status', $criteria['status']);
        }

        if (isset($criteria['active'])) {
            $query->active();
        }

        if (isset($criteria['type'])) {
            $query->where('type', $criteria['type']);
        }

        if (isset($criteria['product_id'])) {
            $query->whereHas('products', function ($q) use ($criteria) {
                $q->where('products.id', $criteria['product_id']);
            });
        }

        return $query->with('products')
            ->orderBy('created_at', 'desc')
            ->paginate($criteria['per_page'] ?? 15);
    }
}

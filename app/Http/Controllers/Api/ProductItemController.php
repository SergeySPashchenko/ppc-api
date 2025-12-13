<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiProductItemResource;
use App\Models\ProductItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductItemController extends Controller
{
    /**
     * Display a listing of accessible product items.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        if (! $user) {
            return ApiProductItemResource::collection(collect());
        }

        // Check policy permission
        $this->authorize('viewAny', ProductItem::class);

        $query = ProductItem::query()->accessibleBy($user);

        // Optional filtering by product_id
        // Note: accessibleBy scope ensures user can only see items from accessible products
        if ($request->has('product_id')) {
            $productId = $request->integer('product_id');
            // Verify user has access to this product
            $product = \App\Models\Product::query()->accessibleBy($user, $productId)->first();
            if ($product) {
                $query->where('ProductID', $productId);
            } else {
                // User doesn't have access to this product, return empty collection
                return ApiProductItemResource::collection(collect());
            }
        }

        // Optional filtering by brand_id (through product relationship)
        // Note: accessibleBy scope ensures user can only see items from accessible brands
        if ($request->has('brand_id')) {
            $brandId = $request->integer('brand_id');
            // Verify user has access to this brand
            $brand = \App\Models\Brand::query()->accessibleBy($user, $brandId)->first();
            if ($brand) {
                $query->whereHas('product', function ($q) use ($brandId) {
                    $q->where('brand_id', $brandId);
                });
            } else {
                // User doesn't have access to this brand, return empty collection
                return ApiProductItemResource::collection(collect());
            }
        }

        // Eager load product relationship
        $query->with('product.brand');

        return ApiProductItemResource::collection($query->get());
    }

    /**
     * Display the specified product item.
     */
    public function show(Request $request, string $id): JsonResponse|ApiProductItemResource
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // First check if product item exists
        $productItem = ProductItem::query()
            ->with('product.brand')
            ->find((int) $id);

        if (! $productItem) {
            return response()->json(['message' => 'Product item not found'], 404);
        }

        // Check policy permission (includes access control check)
        $this->authorize('view', $productItem);

        return new ApiProductItemResource($productItem);
    }
}

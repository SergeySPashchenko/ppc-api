<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * Display a listing of accessible products.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        if (! $user) {
            return ApiProductResource::collection(collect());
        }

        // Check policy permission
        $this->authorize('viewAny', Product::class);

        $query = Product::query()->accessibleBy($user);

        // Optional filtering by brand_id
        // Note: accessibleBy scope ensures user can only see products from accessible brands
        if ($request->has('brand_id')) {
            $brandId = $request->integer('brand_id');
            // Verify user has access to this brand
            $brand = \App\Models\Brand::query()->accessibleBy($user, $brandId)->first();
            if ($brand) {
                $query->where('brand_id', $brandId);
            } else {
                // User doesn't have access to this brand, return empty collection
                return ApiProductResource::collection(collect());
            }
        }

        // Eager load brand relationship
        $query->with('brand');

        return ApiProductResource::collection($query->get());
    }

    /**
     * Display the specified product.
     */
    public function show(Request $request, string $id): JsonResponse|ApiProductResource
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // First check if product exists
        $product = Product::query()
            ->with('brand')
            ->find((int) $id);

        if (! $product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        // Check policy permission (includes access control check)
        $this->authorize('view', $product);

        return new ApiProductResource($product);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiOrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    /**
     * Display a listing of accessible orders.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        if (! $user) {
            return ApiOrderResource::collection(collect());
        }

        // Check policy permission
        $this->authorize('viewAny', Order::class);

        $query = Order::query()->accessibleBy($user);

        // Optional filtering by customer_id
        if ($request->has('customer_id')) {
            $customerId = $request->integer('customer_id');
            $customer = \App\Models\Customer::query()->accessibleBy($user, $customerId)->first();
            if ($customer) {
                $query->where('customer_id', $customerId);
            } else {
                return ApiOrderResource::collection(collect());
            }
        }

        // Optional filtering by product_id (BrandID)
        if ($request->has('product_id')) {
            $productId = $request->integer('product_id');
            $product = \App\Models\Product::query()->accessibleBy($user, $productId)->first();
            if ($product) {
                $query->where('BrandID', $productId);
            } else {
                return ApiOrderResource::collection(collect());
            }
        }

        // Eager load relationships
        $query->with(['customer', 'product', 'orderItems.item', 'addresses']);

        return ApiOrderResource::collection($query->get());
    }

    /**
     * Display the specified order.
     */
    public function show(Request $request, string $id): JsonResponse|ApiOrderResource
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $order = Order::query()
            ->with(['customer', 'product', 'orderItems.item', 'addresses'])
            ->find((int) $id);

        if (! $order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        // Check policy permission (includes access control check)
        $this->authorize('view', $order);

        return new ApiOrderResource($order);
    }
}

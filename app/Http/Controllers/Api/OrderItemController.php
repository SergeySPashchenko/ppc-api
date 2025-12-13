<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiOrderItemResource;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderItemController extends Controller
{
    /**
     * Display a listing of accessible order items.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        if (! $user) {
            return ApiOrderItemResource::collection(collect());
        }

        // Check policy permission
        $this->authorize('viewAny', OrderItem::class);

        $query = OrderItem::query()->accessibleBy($user);

        // Optional filtering by order_id
        if ($request->has('order_id')) {
            $orderId = $request->integer('order_id');
            $order = \App\Models\Order::query()->accessibleBy($user, $orderId)->first();
            if ($order) {
                $query->where('OrderID', $orderId);
            } else {
                return ApiOrderItemResource::collection(collect());
            }
        }

        // Optional filtering by item_id (ProductItem)
        if ($request->has('item_id')) {
            $itemId = $request->integer('item_id');
            $item = \App\Models\ProductItem::query()->accessibleBy($user, $itemId)->first();
            if ($item) {
                $query->where('ItemID', $itemId);
            } else {
                return ApiOrderItemResource::collection(collect());
            }
        }

        // Eager load relationships
        $query->with(['order', 'item']);

        return ApiOrderItemResource::collection($query->get());
    }

    /**
     * Display the specified order item.
     */
    public function show(Request $request, string $id): JsonResponse|ApiOrderItemResource
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $orderItem = OrderItem::query()
            ->with(['order', 'item'])
            ->find((int) $id);

        if (! $orderItem) {
            return response()->json(['message' => 'Order item not found'], 404);
        }

        // Check policy permission (includes access control check)
        $this->authorize('view', $orderItem);

        return new ApiOrderItemResource($orderItem);
    }
}

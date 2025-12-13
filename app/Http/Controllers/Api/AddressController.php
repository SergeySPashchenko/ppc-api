<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiAddressResource;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AddressController extends Controller
{
    /**
     * Display a listing of accessible addresses.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        if (! $user) {
            return ApiAddressResource::collection(collect());
        }

        // Check policy permission
        $this->authorize('viewAny', Address::class);

        $query = Address::query()->accessibleBy($user);

        // Optional filtering by customer_id
        if ($request->has('customer_id')) {
            $customerId = $request->integer('customer_id');
            $customer = \App\Models\Customer::query()->accessibleBy($user, $customerId)->first();
            if ($customer) {
                $query->where('customer_id', $customerId);
            } else {
                return ApiAddressResource::collection(collect());
            }
        }

        // Eager load relationships
        $query->with(['customer', 'orders']);

        return ApiAddressResource::collection($query->get());
    }

    /**
     * Display the specified address.
     */
    public function show(Request $request, string $id): JsonResponse|ApiAddressResource
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $address = Address::query()
            ->with(['customer', 'orders'])
            ->find((int) $id);

        if (! $address) {
            return response()->json(['message' => 'Address not found'], 404);
        }

        // Check policy permission (includes access control check)
        $this->authorize('view', $address);

        return new ApiAddressResource($address);
    }
}

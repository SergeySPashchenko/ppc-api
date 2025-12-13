<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiCustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    /**
     * Display a listing of accessible customers.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        if (! $user) {
            return ApiCustomerResource::collection(collect());
        }

        // Check policy permission
        $this->authorize('viewAny', Customer::class);

        $query = Customer::query()->accessibleBy($user);

        // Eager load relationships
        $query->with(['orders', 'addresses']);

        return ApiCustomerResource::collection($query->get());
    }

    /**
     * Display the specified customer.
     */
    public function show(Request $request, string $id): JsonResponse|ApiCustomerResource
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $customer = Customer::query()
            ->with(['orders', 'addresses'])
            ->find((int) $id);

        if (! $customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        // Check policy permission (includes access control check)
        $this->authorize('view', $customer);

        return new ApiCustomerResource($customer);
    }
}

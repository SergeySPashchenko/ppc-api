<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiBrandResource;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BrandController extends Controller
{
    /**
     * Display a listing of accessible brands.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        if (! $user) {
            return ApiBrandResource::collection(collect());
        }

        // Check policy permission
        $this->authorize('viewAny', Brand::class);

        $query = Brand::query()->accessibleBy($user);

        return ApiBrandResource::collection($query->get());
    }

    /**
     * Display the specified brand.
     */
    public function show(Request $request, string $id): JsonResponse|ApiBrandResource
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // First check if brand exists
        $brand = Brand::query()->find((int) $id);

        if (! $brand) {
            return response()->json(['message' => 'Brand not found'], 404);
        }

        // Check policy permission (includes access control check)
        $this->authorize('view', $brand);

        return new ApiBrandResource($brand);
    }
}

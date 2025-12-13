<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiCategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    /**
     * Display a listing of accessible categories.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        if (! $user) {
            return ApiCategoryResource::collection(collect());
        }

        // Categories are accessible if user has any brand or product access
        // Policy allows viewAny for users with any access, but we filter data here
        if ($user->isGlobalAdmin() || $user->hasAnyBrandOrProductAccess()) {
            // Check policy permission only if user has access
            $this->authorize('viewAny', Category::class);

            return ApiCategoryResource::collection(Category::all());
        }

        // User without access gets empty collection (not 403)
        return ApiCategoryResource::collection(collect());
    }

    /**
     * Display the specified category.
     */
    public function show(Request $request, string $id): JsonResponse|ApiCategoryResource
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $category = Category::query()->find((int) $id);

        if (! $category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        // Check policy permission
        $this->authorize('view', $category);

        return new ApiCategoryResource($category);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiGenderResource;
use App\Models\Gender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GenderController extends Controller
{
    /**
     * Display a listing of accessible genders.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        if (! $user) {
            return ApiGenderResource::collection(collect());
        }

        // Genders are accessible if user has any brand or product access
        if ($user->isGlobalAdmin() || $user->hasAnyBrandOrProductAccess()) {
            // Check policy permission only if user has access
            $this->authorize('viewAny', Gender::class);

            return ApiGenderResource::collection(Gender::all());
        }

        // User without access gets empty collection (not 403)
        return ApiGenderResource::collection(collect());
    }

    /**
     * Display the specified gender.
     */
    public function show(Request $request, string $id): JsonResponse|ApiGenderResource
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $gender = Gender::query()->find((int) $id);

        if (! $gender) {
            return response()->json(['message' => 'Gender not found'], 404);
        }

        // Check policy permission
        $this->authorize('view', $gender);

        return new ApiGenderResource($gender);
    }
}

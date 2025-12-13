<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiExpensetypeResource;
use App\Models\Expensetype;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExpensetypeController extends Controller
{
    /**
     * Display a listing of accessible expense types.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        if (! $user) {
            return ApiExpensetypeResource::collection(collect());
        }

        // Expensetypes are accessible if user has any brand or product access
        if ($user->isGlobalAdmin() || $user->hasAnyBrandOrProductAccess()) {
            // Check policy permission only if user has access
            $this->authorize('viewAny', Expensetype::class);

            return ApiExpensetypeResource::collection(Expensetype::all());
        }

        // User without access gets empty collection (not 403)
        return ApiExpensetypeResource::collection(collect());
    }

    /**
     * Display the specified expense type.
     */
    public function show(Request $request, string $id): JsonResponse|ApiExpensetypeResource
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $expensetype = Expensetype::query()->find((int) $id);

        if (! $expensetype) {
            return response()->json(['message' => 'Expense type not found'], 404);
        }

        // Check policy permission
        $this->authorize('view', $expensetype);

        return new ApiExpensetypeResource($expensetype);
    }
}

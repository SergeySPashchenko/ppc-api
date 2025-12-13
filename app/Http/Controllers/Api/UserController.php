<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    /**
     * Display a listing of accessible users.
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return ApiUserResource::collection(collect());
        }

        // Global admins see all users
        if ($user->isGlobalAdmin()) {
            // Check policy permission for global admins
            $this->authorize('viewAny', User::class);
            $query = User::query();
        } else {
            // Non-admin users can only see themselves (no ViewAny permission needed)
            $query = User::query()->where('id', $user->id);
        }

        return ApiUserResource::collection($query->get());
    }

    /**
     * Display the specified user.
     */
    public function show(Request $request, string $id): JsonResponse|ApiUserResource
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Find user by ID or slug
        $targetUser = User::query()
            ->where('id', $id)
            ->orWhere('slug', $id)
            ->first();

        if (! $targetUser) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Check policy permission (includes self-access check)
        $this->authorize('view', $targetUser);

        return new ApiUserResource($targetUser);
    }
}

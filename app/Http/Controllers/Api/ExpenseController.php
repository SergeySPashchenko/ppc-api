<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiExpenseResource;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExpenseController extends Controller
{
    /**
     * Display a listing of accessible expenses.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        if (! $user) {
            return ApiExpenseResource::collection(collect());
        }

        // Check policy permission
        $this->authorize('viewAny', Expense::class);

        $query = Expense::query()->accessibleBy($user);

        // Optional filtering by product_id
        if ($request->has('product_id')) {
            $productId = $request->integer('product_id');
            $product = \App\Models\Product::query()->accessibleBy($user, $productId)->first();
            if ($product) {
                $query->where('ProductID', $productId);
            } else {
                return ApiExpenseResource::collection(collect());
            }
        }

        // Optional filtering by expense_type_id
        if ($request->has('expense_type_id')) {
            $query->where('ExpenseID', $request->integer('expense_type_id'));
        }

        // Eager load relationships
        $query->with(['product', 'expensetype']);

        return ApiExpenseResource::collection($query->get());
    }

    /**
     * Display the specified expense.
     */
    public function show(Request $request, string $id): JsonResponse|ApiExpenseResource
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $expense = Expense::query()
            ->with(['product', 'expensetype'])
            ->find((int) $id);

        if (! $expense) {
            return response()->json(['message' => 'Expense not found'], 404);
        }

        // Check policy permission (includes access control check)
        $this->authorize('view', $expense);

        return new ApiExpenseResource($expense);
    }
}

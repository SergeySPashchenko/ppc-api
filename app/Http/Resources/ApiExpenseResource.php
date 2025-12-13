<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiExpenseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ProductID' => $this->ProductID,
            'ExpenseID' => $this->ExpenseID,
            'ExpenseDate' => $this->ExpenseDate?->format('Y-m-d'),
            'Expense' => $this->Expense,
            'product' => $this->whenLoaded('product', fn () => new ApiProductResource($this->product)),
            'expensetype' => $this->whenLoaded('expensetype', fn () => new ApiExpensetypeResource($this->expensetype)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}

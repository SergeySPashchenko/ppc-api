<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiOrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'idOrderItem' => $this->idOrderItem,
            'Price' => $this->Price,
            'Qty' => $this->Qty,
            'OrderID' => $this->OrderID,
            'ItemID' => $this->ItemID,
            'order' => $this->whenLoaded('order', fn () => new ApiOrderResource($this->order)),
            'item' => $this->whenLoaded('item', fn () => new ApiProductItemResource($this->item)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}

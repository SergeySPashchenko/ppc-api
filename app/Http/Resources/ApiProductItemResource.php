<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiProductItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ItemID' => $this->ItemID,
            'ProductID' => $this->ProductID,
            'ProductName' => $this->ProductName,
            'slug' => $this->slug,
            'SKU' => $this->SKU,
            'Quantity' => $this->Quantity,
            'upSell' => $this->upSell,
            'extraProduct' => $this->extraProduct,
            'offerProducts' => $this->offerProducts,
            'active' => $this->active,
            'deleted' => $this->deleted,
            'product' => $this->whenLoaded('product', fn () => new ApiProductResource($this->product)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}

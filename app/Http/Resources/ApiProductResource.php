<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ProductID' => $this->ProductID,
            'Product' => $this->Product,
            'slug' => $this->slug,
            'brand_id' => $this->brand_id,
            'brand' => $this->whenLoaded('brand', fn () => new ApiBrandResource($this->brand)),
            'main_category_id' => $this->main_category_id,
            'marketing_category_id' => $this->marketing_category_id,
            'gender_id' => $this->gender_id,
            'newSystem' => $this->newSystem,
            'Visible' => $this->Visible,
            'flyer' => $this->flyer,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}

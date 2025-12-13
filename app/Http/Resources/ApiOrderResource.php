<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiOrderResource extends JsonResource
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
            'OrderID' => $this->OrderID,
            'Agent' => $this->Agent,
            'Created' => $this->Created?->toIso8601String(),
            'OrderDate' => $this->OrderDate?->format('Y-m-d'),
            'OrderNum' => $this->OrderNum,
            'ProductTotal' => $this->ProductTotal,
            'GrandTotal' => $this->GrandTotal,
            'RefundAmount' => $this->RefundAmount,
            'Shipping' => $this->Shipping,
            'ShippingMethod' => $this->ShippingMethod,
            'Refund' => $this->Refund,
            'customer_id' => $this->customer_id,
            'BrandID' => $this->BrandID,
            'customer' => $this->whenLoaded('customer', fn () => new ApiCustomerResource($this->customer)),
            'product' => $this->whenLoaded('product', fn () => new ApiProductResource($this->product)),
            'orderItems' => $this->whenLoaded('orderItems', fn () => ApiOrderItemResource::collection($this->orderItems)),
            'addresses' => $this->whenLoaded('addresses', fn () => ApiAddressResource::collection($this->addresses)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}

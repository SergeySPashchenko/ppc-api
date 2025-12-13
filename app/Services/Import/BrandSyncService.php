<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\Brand;
use Illuminate\Support\Facades\Log;

/**
 * Service for syncing brands from external data.
 * Creates brands if they don't exist, updates if changed.
 */
final class BrandSyncService
{
    /**
     * Sync brand from external product data.
     * Creates brand if doesn't exist, updates if changed.
     */
    public function syncBrand(?string $brandName): ?Brand
    {
        if (empty($brandName)) {
            return null;
        }

        $brand = Brand::query()
            ->where('brand_name', $brandName)
            ->first();

        if ($brand === null) {
            $brand = Brand::create([
                'brand_name' => $brandName,
            ]);
            Log::info('Created new brand', ['brand_id' => $brand->brand_id, 'brand_name' => $brandName]);
        } else {
            // Check if brand name changed (shouldn't happen, but safe)
            if ($brand->brand_name !== $brandName) {
                $brand->brand_name = $brandName;
                $brand->save();
                Log::info('Updated brand', ['brand_id' => $brand->brand_id, 'brand_name' => $brandName]);
            }
        }

        return $brand;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Gender;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

/**
 * Service for syncing products from external data.
 * Creates products if they don't exist, updates if changed.
 */
final class ProductSyncService
{
    public function __construct(
        private readonly BrandSyncService $brandService,
    ) {}

    /**
     * Sync product from external data.
     * Creates product if doesn't exist, updates if changed.
     *
     * @param  array<string, mixed>  $externalData
     */
    public function syncProduct(array $externalData): ?Product
    {
        $productId = $externalData['ProductID'] ?? null;
        if ($productId === null) {
            return null;
        }

        $product = Product::query()
            ->where('ProductID', $productId)
            ->first();

        // Sync brand first
        $brandName = $externalData['Brand'] ?? null;
        $brand = $this->brandService->syncBrand($brandName);

        // Get or create default brand if needed (for NOT NULL constraint)
        if ($brand === null) {
            $defaultBrand = Brand::query()->where('brand_name', 'Default')->first();
            if ($defaultBrand === null) {
                try {
                    $defaultBrand = Brand::create([
                        'brand_name' => 'Default',
                    ]);
                    Log::info('Created default brand for product import', ['brand_id' => $defaultBrand->brand_id]);
                } catch (\Exception $e) {
                    Log::error('Failed to create default brand', ['error' => $e->getMessage()]);
                    throw $e;
                }
            }
            $brand = $defaultBrand;
        }

        if ($product === null) {
            // Create new product
            $productName = $externalData['Product'] ?? "Product {$productId}";
            // Get or create default category if needed (for NOT NULL constraint)
            $defaultCategory = Category::query()->first();
            if ($defaultCategory === null) {
                try {
                    $defaultCategory = Category::create([
                        'category_name' => 'Default',
                    ]);
                    Log::info('Created default category for product import', ['category_id' => $defaultCategory->category_id]);
                } catch (\Exception $e) {
                    Log::error('Failed to create default category', ['error' => $e->getMessage()]);
                    throw $e;
                }
            }
            $defaultCategoryId = $defaultCategory->category_id;

            // Get or create default gender if needed (for NOT NULL constraint)
            $defaultGender = Gender::query()->first();
            if ($defaultGender === null) {
                try {
                    $defaultGender = Gender::create([
                        'gender_name' => 'Unisex',
                    ]);
                    Log::info('Created default gender for product import', ['gender_id' => $defaultGender->gender_id]);
                } catch (\Exception $e) {
                    Log::error('Failed to create default gender', ['error' => $e->getMessage()]);
                    throw $e;
                }
            }
            $defaultGenderId = $defaultGender->gender_id;

            $product = Product::create([
                'ProductID' => $productId,
                'Product' => $productName,
                'newSystem' => $externalData['newSystem'] ?? false,
                'Visible' => $externalData['Visible'] ?? false,
                'flyer' => $externalData['flyer'] ?? 0, // Default to 0 if not provided
                'main_category_id' => $externalData['main_category_id'] ?? $defaultCategoryId,
                'marketing_category_id' => $externalData['marketing_category_id'] ?? $defaultCategoryId,
                'gender_id' => $externalData['gender_id'] ?? $defaultGenderId,
                'brand_id' => $brand->brand_id,
            ]);
            Log::info('Created new product', ['ProductID' => $productId, 'Product' => $productName]);
        } else {
            // Check if product data changed
            $changed = false;
            $productName = $externalData['Product'] ?? null;

            // Update Product name separately (only if provided and different)
            if ($productName !== null && $productName !== $product->Product) {
                $product->Product = $productName;
                $changed = true;
            }

            // Update brand_id if changed
            if ($brand->brand_id !== $product->brand_id) {
                $product->brand_id = $brand->brand_id;
                $changed = true;
            }

            // Update other fields only if explicitly provided
            $fields = [
                'newSystem' => $externalData['newSystem'] ?? null,
                'Visible' => $externalData['Visible'] ?? null,
                'flyer' => $externalData['flyer'] ?? null,
                'main_category_id' => $externalData['main_category_id'] ?? null,
                'marketing_category_id' => $externalData['marketing_category_id'] ?? null,
                'gender_id' => $externalData['gender_id'] ?? null,
            ];

            foreach ($fields as $field => $value) {
                if ($value !== null && $value != $product->$field) {
                    $product->$field = $value;
                    $changed = true;
                }
            }

            if ($changed) {
                $product->save();
                Log::info('Updated product', ['ProductID' => $productId]);
            }
        }

        return $product;
    }
}

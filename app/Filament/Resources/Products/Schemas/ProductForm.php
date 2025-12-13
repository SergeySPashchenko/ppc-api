<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        $user = Auth::user();

        return $schema
            ->components([
                TextInput::make('Product')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                Toggle::make('newSystem')
                    ->required(),
                Toggle::make('Visible')
                    ->required(),
                Toggle::make('flyer')
                    ->required(),
                Select::make('main_category_id')
                    ->relationship(
                        'mainCategory',
                        'category_name',
                        modifyQueryUsing: function (Builder $query) use ($user) {
                            // If user has access to any brand/product, show all categories
                            if ($user?->hasAnyBrandOrProductAccess()) {
                                return $query;
                            }

                            // Otherwise, show only categories from accessible products
                            $accessibleProductIds = $user?->getAccessibleProductIds() ?? collect();
                            if ($accessibleProductIds->isNotEmpty()) {
                                return $query->whereHas('products', function (Builder $q) use ($accessibleProductIds) {
                                    $q->whereIn('ProductID', $accessibleProductIds);
                                });
                            }

                            return $query->whereRaw('1 = 0'); // No access
                        }
                    )
                    ->required(),
                Select::make('marketing_category_id')
                    ->relationship(
                        'marketingCategory',
                        'category_name',
                        modifyQueryUsing: function (Builder $query) use ($user) {
                            // If user has access to any brand/product, show all categories
                            if ($user?->hasAnyBrandOrProductAccess()) {
                                return $query;
                            }

                            // Otherwise, show only categories from accessible products
                            $accessibleProductIds = $user?->getAccessibleProductIds() ?? collect();
                            if ($accessibleProductIds->isNotEmpty()) {
                                return $query->whereHas('products', function (Builder $q) use ($accessibleProductIds) {
                                    $q->whereIn('ProductID', $accessibleProductIds);
                                });
                            }

                            return $query->whereRaw('1 = 0'); // No access
                        }
                    )
                    ->required(),
                Select::make('gender_id')
                    ->relationship(
                        'gender',
                        'gender_name',
                        modifyQueryUsing: function (Builder $query) use ($user) {
                            // If user has access to any brand/product, show all genders
                            if ($user?->hasAnyBrandOrProductAccess()) {
                                return $query;
                            }

                            // Otherwise, show only genders from accessible products
                            $accessibleProductIds = $user?->getAccessibleProductIds() ?? collect();
                            if ($accessibleProductIds->isNotEmpty()) {
                                return $query->whereHas('products', function (Builder $q) use ($accessibleProductIds) {
                                    $q->whereIn('ProductID', $accessibleProductIds);
                                });
                            }

                            return $query->whereRaw('1 = 0'); // No access
                        }
                    )
                    ->required(),
                Select::make('brand_id')
                    ->relationship(
                        'brand',
                        'brand_name',
                        modifyQueryUsing: function (Builder $query) use ($user) {
                            // Global admins see all brands
                            if ($user?->isGlobalAdmin()) {
                                return $query;
                            }

                            // Filter by accessible brands
                            $accessibleBrandIds = $user?->getAccessibleBrandIds() ?? collect();
                            if ($accessibleBrandIds->isNotEmpty()) {
                                return $query->whereIn('brand_id', $accessibleBrandIds);
                            }

                            return $query->whereRaw('1 = 0'); // No access
                        }
                    )
                    ->required(),
            ]);
    }
}

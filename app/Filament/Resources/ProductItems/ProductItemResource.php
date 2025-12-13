<?php

namespace App\Filament\Resources\ProductItems;

use App\Filament\Resources\ProductItems\Pages\CreateProductItem;
use App\Filament\Resources\ProductItems\Pages\EditProductItem;
use App\Filament\Resources\ProductItems\Pages\ListProductItems;
use App\Filament\Resources\ProductItems\Schemas\ProductItemForm;
use App\Filament\Resources\ProductItems\Tables\ProductItemsTable;
use App\Models\AllBrandsTenant;
use App\Models\ProductItem;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class ProductItemResource extends Resource
{
    protected static ?string $model = ProductItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ProductItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductItemsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductItems::route('/'),
            'create' => CreateProductItem::route('/create'),
            'edit' => EditProductItem::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $tenant = Filament::getTenant();

        // If "All Brands" is selected, disable tenant scope to show all accessible product items
        if ($tenant instanceof AllBrandsTenant) {
            try {
                $tenantScopeName = Filament::getTenancyScopeName();
                $query->withoutGlobalScope($tenantScopeName);
            } catch (\Exception $e) {
                // Scope might not be registered yet, ignore
            }
        }

        // Apply access filtering for non-global admins
        if (Auth::check() && ! Auth::user()->isGlobalAdmin()) {
            $query->accessibleBy(Auth::user());
        }

        return $query;
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return static::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

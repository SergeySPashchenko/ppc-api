<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('Product'),
                TextEntry::make('slug'),
                IconEntry::make('newSystem')
                    ->boolean(),
                IconEntry::make('Visible')
                    ->boolean(),
                IconEntry::make('flyer')
                    ->boolean(),
                TextEntry::make('mainCategory.category_id')
                    ->label('Main category'),
                TextEntry::make('marketingCategory.category_id')
                    ->label('Marketing category'),
                TextEntry::make('gender.gender_id')
                    ->label('Gender'),
                TextEntry::make('brand.brand_id')
                    ->label('Brand'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Product $record): bool => $record->trashed()),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}

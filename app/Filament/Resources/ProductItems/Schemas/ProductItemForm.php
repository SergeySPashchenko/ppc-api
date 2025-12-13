<?php

namespace App\Filament\Resources\ProductItems\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('ProductID')
                    ->numeric(),
                TextInput::make('ProductName')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('SKU')
                    ->required(),
                TextInput::make('Quantity')
                    ->required()
                    ->numeric(),
                Toggle::make('upSell')
                    ->required(),
                Toggle::make('active')
                    ->required(),
                Toggle::make('deleted')
                    ->required(),
                TextInput::make('offerProducts'),
                Toggle::make('extraProduct')
                    ->required(),
            ]);
    }
}

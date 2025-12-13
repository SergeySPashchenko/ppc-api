<?php

namespace App\Filament\Resources\OrderItems\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OrderItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('OrderID')
                    ->numeric(),
                TextInput::make('ItemID')
                    ->numeric(),
                TextInput::make('Price')
                    ->required()
                    ->numeric(),
                TextInput::make('Qty')
                    ->required()
                    ->numeric(),
            ]);
    }
}

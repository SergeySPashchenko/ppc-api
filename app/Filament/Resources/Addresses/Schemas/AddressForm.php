<?php

namespace App\Filament\Resources\Addresses\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AddressForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('type')
                    ->required()
                    ->default('both'),
                TextInput::make('name'),
                TextInput::make('address'),
                TextInput::make('address2'),
                TextInput::make('city'),
                TextInput::make('state'),
                TextInput::make('zip'),
                TextInput::make('country'),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('address_hash'),
                Select::make('customer_id')
                    ->relationship('customer', 'name'),
            ]);
    }
}

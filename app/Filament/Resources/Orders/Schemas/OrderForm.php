<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('OrderID')
                    ->required()
                    ->numeric(),
                TextInput::make('Agent')
                    ->required(),
                DateTimePicker::make('Created')
                    ->required(),
                DatePicker::make('OrderDate')
                    ->required(),
                TextInput::make('OrderNum')
                    ->required(),
                TextInput::make('ProductTotal')
                    ->required()
                    ->numeric(),
                TextInput::make('GrandTotal')
                    ->required()
                    ->numeric(),
                TextInput::make('RefundAmount')
                    ->required()
                    ->numeric(),
                TextInput::make('Shipping'),
                TextInput::make('ShippingMethod'),
                Toggle::make('Refund')
                    ->required(),
                Select::make('customer_id')
                    ->relationship('customer', 'name'),
            ]);
    }
}

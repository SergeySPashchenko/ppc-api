<?php

namespace App\Filament\Resources\Expenses\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('ExpenseDate')
                    ->required(),
                TextInput::make('Expense')
                    ->required()
                    ->numeric(),
                TextInput::make('ProductID')
                    ->numeric(),
                TextInput::make('ExpenseID')
                    ->numeric(),
            ]);
    }
}

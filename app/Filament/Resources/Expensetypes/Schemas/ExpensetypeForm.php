<?php

namespace App\Filament\Resources\Expensetypes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ExpensetypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('Name')
                    ->required(),
                Toggle::make('Visible')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
            ]);
    }
}

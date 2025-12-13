<?php

namespace App\Filament\Resources\Genders\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GenderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('gender_name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
            ]);
    }
}

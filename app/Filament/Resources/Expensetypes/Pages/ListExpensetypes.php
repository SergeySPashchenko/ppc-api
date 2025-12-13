<?php

namespace App\Filament\Resources\Expensetypes\Pages;

use App\Filament\Resources\Expensetypes\ExpensetypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExpensetypes extends ListRecords
{
    protected static string $resource = ExpensetypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

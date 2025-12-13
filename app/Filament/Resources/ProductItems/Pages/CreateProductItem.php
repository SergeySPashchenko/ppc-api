<?php

namespace App\Filament\Resources\ProductItems\Pages;

use App\Filament\Resources\ProductItems\ProductItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductItem extends CreateRecord
{
    protected static string $resource = ProductItemResource::class;
}

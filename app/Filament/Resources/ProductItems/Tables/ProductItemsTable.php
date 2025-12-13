<?php

namespace App\Filament\Resources\ProductItems\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ProductItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ProductID')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('ProductName')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('SKU')
                    ->searchable(),
                TextColumn::make('Quantity')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('upSell')
                    ->boolean(),
                IconColumn::make('active')
                    ->boolean(),
                IconColumn::make('deleted')
                    ->boolean(),
                TextColumn::make('offerProducts')
                    ->searchable(),
                IconColumn::make('extraProduct')
                    ->boolean(),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}

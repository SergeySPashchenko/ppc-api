<?php

namespace App\Filament\Resources\Orders\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('OrderID')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('Agent')
                    ->searchable(),
                TextColumn::make('Created')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('OrderDate')
                    ->date()
                    ->sortable(),
                TextColumn::make('OrderNum')
                    ->searchable(),
                TextColumn::make('ProductTotal')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('GrandTotal')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('RefundAmount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('Shipping')
                    ->searchable(),
                TextColumn::make('ShippingMethod')
                    ->searchable(),
                IconColumn::make('Refund')
                    ->boolean(),
                TextColumn::make('customer.name')
                    ->searchable(),
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

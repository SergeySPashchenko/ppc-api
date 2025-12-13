<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Brand;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('Product')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                IconColumn::make('newSystem')
                    ->boolean(),
                IconColumn::make('Visible')
                    ->boolean(),
                IconColumn::make('flyer')
                    ->boolean(),
                TextColumn::make('mainCategory.category_id')
                    ->searchable(),
                TextColumn::make('marketingCategory.category_id')
                    ->searchable(),
                TextColumn::make('gender.gender_id')
                    ->searchable(),
                TextColumn::make('brand.brand_name')
                    ->label('Brand')
                    ->searchable()
                    ->sortable(),
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
                SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->options(function () {
                        $query = Brand::query();

                        // For non-global admins, filter by accessible brands
                        if (Auth::check() && ! Auth::user()->isGlobalAdmin()) {
                            $query->accessibleBy(Auth::user());
                        }

                        return $query->pluck('brand_name', 'brand_id')->toArray();
                    })
                    ->searchable()
                    ->preload(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
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

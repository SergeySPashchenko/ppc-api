<?php

namespace App\Filament\Resources\Genders;

use App\Filament\Resources\Genders\Pages\CreateGender;
use App\Filament\Resources\Genders\Pages\EditGender;
use App\Filament\Resources\Genders\Pages\ListGenders;
use App\Filament\Resources\Genders\Schemas\GenderForm;
use App\Filament\Resources\Genders\Tables\GendersTable;
use App\Models\Gender;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class GenderResource extends Resource
{
    protected static ?string $model = Gender::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return GenderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GendersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGenders::route('/'),
            'create' => CreateGender::route('/create'),
            'edit' => EditGender::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // Genders are accessible if user has any brand or product access
        // Global admins see all
        if (Auth::check() && ! Auth::user()->isGlobalAdmin()) {
            $user = Auth::user();
            if (! $user->hasAnyBrandOrProductAccess()) {
                // User has no access, return empty query
                return $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return static::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

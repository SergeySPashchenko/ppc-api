<?php

namespace App\Filament\Resources\Expensetypes;

use App\Filament\Resources\Expensetypes\Pages\CreateExpensetype;
use App\Filament\Resources\Expensetypes\Pages\EditExpensetype;
use App\Filament\Resources\Expensetypes\Pages\ListExpensetypes;
use App\Filament\Resources\Expensetypes\Schemas\ExpensetypeForm;
use App\Filament\Resources\Expensetypes\Tables\ExpensetypesTable;
use App\Models\Expensetype;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class ExpensetypeResource extends Resource
{
    protected static ?string $model = Expensetype::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ExpensetypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExpensetypesTable::configure($table);
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
            'index' => ListExpensetypes::route('/'),
            'create' => CreateExpensetype::route('/create'),
            'edit' => EditExpensetype::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // Expensetypes are accessible if user has any brand or product access
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

<?php

namespace App\Filament\Pdv\Resources\Gastos;

use App\Filament\Pdv\Resources\Gastos\Pages\CreateGasto;
use App\Filament\Pdv\Resources\Gastos\Pages\EditGasto;
use App\Filament\Pdv\Resources\Gastos\Pages\ListGastos;
use App\Filament\Pdv\Resources\Gastos\Schemas\GastoForm;
use App\Filament\Pdv\Resources\Gastos\Tables\GastosTable;
use App\Models\Gasto;
use BackedEnum;
use UnitEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class GastoResource extends Resource
{
    protected static ?string $model = Gasto::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Gastos';

    protected static string|UnitEnum|null $navigationGroup = 'Gastos';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Gasto';

    protected static ?string $pluralModelLabel = 'Gastos';

    protected static ?string $recordTitleAttribute = 'descripcion';

    public static function canAccess(): bool            { return Filament::getTenant()->tieneModulo('gastos') && (auth()->user()?->can('gastos.ver') ?? false); }
    public static function canCreate(): bool            { return auth()->user()?->can('gastos.crear') ?? false; }
    public static function canEdit(Model $r): bool      { return (auth()->user()?->can('gastos.crear') ?? false) && ! $r->estaAnulado(); }
    public static function canDelete(Model $r): bool    { return false; }

    public static function form(Schema $schema): Schema
    {
        return GastoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GastosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListGastos::route('/'),
            'create' => CreateGasto::route('/create'),
            'edit'   => EditGasto::route('/{record}/edit'),
        ];
    }
}

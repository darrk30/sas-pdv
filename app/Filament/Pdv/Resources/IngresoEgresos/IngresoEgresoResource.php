<?php

namespace App\Filament\Pdv\Resources\IngresoEgresos;

use App\Filament\Pdv\Resources\IngresoEgresos\Pages\CreateIngresoEgreso;
use App\Filament\Pdv\Resources\IngresoEgresos\Pages\EditIngresoEgreso;
use App\Filament\Pdv\Resources\IngresoEgresos\Pages\ListIngresoEgresos;
use App\Filament\Pdv\Resources\IngresoEgresos\Schemas\IngresoEgresoForm;
use App\Filament\Pdv\Resources\IngresoEgresos\Tables\IngresoEgresosTable;
use App\Models\IngresoEgreso;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class IngresoEgresoResource extends Resource
{
    protected static ?string $model = IngresoEgreso::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Ingresos y Egresos';

    protected static string|UnitEnum|null $navigationGroup = 'Caja';

    protected static ?string $modelLabel = 'Movimiento';

    protected static ?string $pluralModelLabel = 'Ingresos y Egresos';

    protected static ?string $recordTitleAttribute = 'motivo';

    public static function form(Schema $schema): Schema
    {
        return IngresoEgresoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IngresoEgresosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListIngresoEgresos::route('/'),
            'create' => CreateIngresoEgreso::route('/create'),
            'edit'   => EditIngresoEgreso::route('/{record}/edit'),
        ];
    }
}

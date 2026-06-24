<?php

namespace App\Filament\Pdv\Resources\SesionCajas;

use App\Filament\Pdv\Resources\SesionCajas\Pages\CreateSesionCaja;
use App\Filament\Pdv\Resources\SesionCajas\Pages\EditSesionCaja;
use App\Filament\Pdv\Resources\SesionCajas\Pages\ListSesionCajas;
use App\Filament\Pdv\Resources\SesionCajas\Schemas\SesionCajaForm;
use App\Filament\Pdv\Resources\SesionCajas\Tables\SesionCajasTable;
use App\Models\SesionCaja;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SesionCajaResource extends Resource
{
    protected static ?string $model = SesionCaja::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $navigationLabel = 'Sesiones de Caja';

    protected static string|UnitEnum|null $navigationGroup = 'Caja';

    protected static ?string $modelLabel = 'Sesión de Caja';

    protected static ?string $pluralModelLabel = 'Sesiones de Caja';

    protected static ?string $recordTitleAttribute = 'fecha_apertura';

    public static function form(Schema $schema): Schema
    {
        return SesionCajaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SesionCajasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListSesionCajas::route('/'),
            'create' => CreateSesionCaja::route('/create'),
            'edit'   => EditSesionCaja::route('/{record}/edit'),
        ];
    }
}

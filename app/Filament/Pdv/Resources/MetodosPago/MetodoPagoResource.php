<?php

namespace App\Filament\Pdv\Resources\MetodosPago;

use App\Filament\Pdv\Resources\MetodosPago\Pages\CreateMetodoPago;
use App\Filament\Pdv\Resources\MetodosPago\Pages\EditMetodoPago;
use App\Filament\Pdv\Resources\MetodosPago\Pages\ListMetodosPago;
use App\Filament\Pdv\Resources\MetodosPago\Schemas\MetodoPagoForm;
use App\Filament\Pdv\Resources\MetodosPago\Tables\MetodosPagoTable;
use App\Models\MetodoPago;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MetodoPagoResource extends Resource
{
    protected static ?string $model = MetodoPago::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $recordTitleAttribute = 'nombre';

    protected static ?string $navigationLabel = 'Métodos de Pago';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?string $modelLabel = 'Método de Pago';

    protected static ?string $pluralModelLabel = 'Métodos de Pago';

    public static function form(Schema $schema): Schema
    {
        return MetodoPagoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MetodosPagoTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListMetodosPago::route('/'),
            'create' => CreateMetodoPago::route('/create'),
            'edit'   => EditMetodoPago::route('/{record}/edit'),
        ];
    }
}

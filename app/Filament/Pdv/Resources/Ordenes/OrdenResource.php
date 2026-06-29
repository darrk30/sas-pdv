<?php

namespace App\Filament\Pdv\Resources\Ordenes;

use App\Filament\Pdv\Resources\Ordenes\Pages\CreateOrden;
use App\Filament\Pdv\Resources\Ordenes\Pages\EditOrden;
use App\Filament\Pdv\Resources\Ordenes\Pages\ListOrdenes;
use App\Filament\Pdv\Resources\Ordenes\Pages\ViewOrden;
use App\Filament\Pdv\Resources\Ordenes\Schemas\OrdenForm;
use App\Filament\Pdv\Resources\Ordenes\Tables\OrdenesTable;
use App\Models\Orden;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OrdenResource extends Resource
{
    protected static ?string $model = Orden::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Órdenes';

    protected static string|UnitEnum|null $navigationGroup = 'Ventas';

    protected static ?string $modelLabel = 'Orden';

    protected static ?string $pluralModelLabel = 'Órdenes';

    protected static ?string $slug = 'ordenes';

    protected static ?string $recordTitleAttribute = 'codigo';

    public static function form(Schema $schema): Schema
    {
        return OrdenForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdenesTable::configure($table)
            ->recordUrl(fn(Orden $record): string =>
                $record->estaCancelada()
                    ? static::getUrl('view', ['record' => $record])
                    : static::getUrl('edit', ['record' => $record])
            );
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListOrdenes::route('/'),
            'create' => CreateOrden::route('/create'),
            'view'   => ViewOrden::route('/{record}'),
            'edit'   => EditOrden::route('/{record}/edit'),
        ];
    }
}

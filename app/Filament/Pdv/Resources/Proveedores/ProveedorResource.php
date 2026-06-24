<?php

namespace App\Filament\Pdv\Resources\Proveedores;

use App\Filament\Pdv\Resources\Proveedores\Pages\CreateProveedor;
use App\Filament\Pdv\Resources\Proveedores\Pages\EditProveedor;
use App\Filament\Pdv\Resources\Proveedores\Pages\ListProveedores;
use App\Filament\Pdv\Resources\Proveedores\Schemas\ProveedorForm;
use App\Filament\Pdv\Resources\Proveedores\Tables\ProveedoresTable;
use App\Models\Proveedor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProveedorResource extends Resource
{
    protected static ?string $model = Proveedor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $recordTitleAttribute = 'nombre';

    protected static ?string $navigationLabel = 'Proveedores';

    protected static ?string $navigationGroup = 'Compras';

    protected static ?string $modelLabel = 'Proveedor';

    protected static ?string $pluralModelLabel = 'Proveedores';

    public static function form(Schema $schema): Schema
    {
        return ProveedorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProveedoresTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListProveedores::route('/'),
            'create' => CreateProveedor::route('/create'),
            'edit'   => EditProveedor::route('/{record}/edit'),
        ];
    }
}

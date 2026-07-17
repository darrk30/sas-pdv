<?php

namespace App\Filament\Pdv\Resources\Proveedores;

use App\Filament\Pdv\Resources\Proveedores\Pages\CreateProveedor;
use App\Filament\Pdv\Resources\Proveedores\Pages\EditProveedor;
use App\Filament\Pdv\Resources\Proveedores\Pages\ListProveedores;
use App\Filament\Pdv\Resources\Proveedores\Schemas\ProveedorForm;
use App\Filament\Pdv\Resources\Proveedores\Tables\ProveedoresTable;
use App\Models\Proveedor;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProveedorResource extends Resource
{
    protected static ?string $model = Proveedor::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $recordTitleAttribute = 'nombre';

    protected static ?string $navigationLabel = 'Proveedores';

    protected static string|UnitEnum|null $navigationGroup = 'Compras';
    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Proveedor';

    protected static ?string $pluralModelLabel = 'Proveedores';

    public static function canAccess(): bool              { return Filament::getTenant()->tieneModulo('proveedores') && (auth()->user()?->can('proveedores.ver') ?? false); }
    public static function canCreate(): bool              { return auth()->user()?->can('proveedores.crear') ?? false; }
    public static function canEdit(Model $record): bool   { return auth()->user()?->can('proveedores.editar') ?? false; }
    public static function canDelete(Model $record): bool { return auth()->user()?->can('proveedores.eliminar') ?? false; }

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

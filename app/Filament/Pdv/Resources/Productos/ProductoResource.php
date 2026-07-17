<?php

namespace App\Filament\Pdv\Resources\Productos;

use App\Filament\Pdv\Resources\Productos\Pages\CreateProducto;
use App\Filament\Pdv\Resources\Productos\Pages\EditProducto;
use App\Filament\Pdv\Resources\Productos\Pages\ListProductos;
use App\Filament\Pdv\Resources\Productos\Schemas\ProductoForm;
use App\Filament\Pdv\Resources\Productos\Tables\ProductosTable;
use App\Models\Producto;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProductoResource extends Resource
{
    protected static ?string $model = Producto::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?string $navigationLabel = 'Productos';

    protected static string|UnitEnum|null $navigationGroup = 'Inventario';
    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Producto';

    protected static ?string $pluralModelLabel = 'Productos';

    protected static ?string $recordTitleAttribute = 'Producto';

    public static function canAccess(): bool              { return Filament::getTenant()->tieneModulo('gestion_productos') && (auth()->user()?->can('productos.ver') ?? false); }
    public static function canCreate(): bool              { return auth()->user()?->can('productos.crear') ?? false; }
    public static function canEdit(Model $record): bool   { return auth()->user()?->can('productos.editar') ?? false; }
    public static function canDelete(Model $record): bool { return auth()->user()?->can('productos.eliminar') ?? false; }

    public static function form(Schema $schema): Schema
    {
        return ProductoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductosTable::configure($table);
    }

    // Excluye productos archivados del listado y de las búsquedas del resource.
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('estado', '!=', 'archivado');
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
            'index' => ListProductos::route('/'),
            'create' => CreateProducto::route('/create'),
            'edit' => EditProducto::route('/{record}/edit'),
        ];
    }
}

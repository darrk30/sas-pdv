<?php

namespace App\Filament\Pdv\Resources\Categorias;

use App\Filament\Pdv\Resources\Categorias\Pages\CreateCategoria;
use App\Filament\Pdv\Resources\Categorias\Pages\EditCategoria;
use App\Filament\Pdv\Resources\Categorias\Pages\ListCategorias;
use App\Filament\Pdv\Resources\Categorias\Schemas\CategoriaForm;
use App\Filament\Pdv\Resources\Categorias\Tables\CategoriasTable;
use App\Models\Categoria;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CategoriaResource extends Resource
{
    protected static ?string $model = Categoria::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationLabel = 'Categorías';

    protected static string|UnitEnum|null $navigationGroup = 'Catálogo';
    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Categoría';

    protected static ?string $pluralModelLabel = 'Categorías';

    protected static ?string $recordTitleAttribute = 'Categoria';

    public static function canAccess(): bool              { return auth()->user()?->can('categorias.ver') ?? false; }
    public static function canCreate(): bool              { return auth()->user()?->can('categorias.crear') ?? false; }
    public static function canEdit(Model $record): bool   { return auth()->user()?->can('categorias.editar') ?? false; }
    public static function canDelete(Model $record): bool { return auth()->user()?->can('categorias.eliminar') ?? false; }

    public static function form(Schema $schema): Schema
    {
        return CategoriaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CategoriasTable::configure($table);
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
            'index' => ListCategorias::route('/'),
            'create' => CreateCategoria::route('/create'),
            'edit' => EditCategoria::route('/{record}/edit'),
        ];
    }
}

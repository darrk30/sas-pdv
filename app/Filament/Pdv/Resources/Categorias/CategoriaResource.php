<?php

namespace App\Filament\Pdv\Resources\Categorias;

use App\Filament\Pdv\Resources\Categorias\Pages\CreateCategoria;
use App\Filament\Pdv\Resources\Categorias\Pages\EditCategoria;
use App\Filament\Pdv\Resources\Categorias\Pages\ListCategorias;
use App\Filament\Pdv\Resources\Categorias\Schemas\CategoriaForm;
use App\Filament\Pdv\Resources\Categorias\Tables\CategoriasTable;
use App\Models\Categoria;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CategoriaResource extends Resource
{
    protected static ?string $model = Categoria::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'Categorías';

    protected static ?string $navigationGroup = 'Catálogo';

    protected static ?string $modelLabel = 'Categoría';

    protected static ?string $pluralModelLabel = 'Categorías';

    protected static ?string $recordTitleAttribute = 'Categoria';

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

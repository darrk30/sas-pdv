<?php

namespace App\Filament\Pdv\Resources\Marcas;

use App\Filament\Pdv\Resources\Marcas\Pages\CreateMarca;
use App\Filament\Pdv\Resources\Marcas\Pages\EditMarca;
use App\Filament\Pdv\Resources\Marcas\Pages\ListMarcas;
use App\Filament\Pdv\Resources\Marcas\Schemas\MarcaForm;
use App\Filament\Pdv\Resources\Marcas\Tables\MarcasTable;
use App\Models\Marca;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MarcaResource extends Resource
{
    protected static ?string $model = Marca::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookmark;

    protected static ?string $navigationLabel = 'Marcas';

    protected static ?string $navigationGroup = 'Catálogo';

    protected static ?string $modelLabel = 'Marca';

    protected static ?string $pluralModelLabel = 'Marcas';

    protected static ?string $recordTitleAttribute = 'Marca';

    public static function form(Schema $schema): Schema
    {
        return MarcaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MarcasTable::configure($table);
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
            'index' => ListMarcas::route('/'),
            'create' => CreateMarca::route('/create'),
            'edit' => EditMarca::route('/{record}/edit'),
        ];
    }
}

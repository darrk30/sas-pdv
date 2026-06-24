<?php

namespace App\Filament\Pdv\Resources\Atributos;

use App\Filament\Pdv\Resources\Atributos\Pages\CreateAtributo;
use App\Filament\Pdv\Resources\Atributos\Pages\EditAtributo;
use App\Filament\Pdv\Resources\Atributos\Pages\ListAtributos;
use App\Filament\Pdv\Resources\Atributos\Schemas\AtributoForm;
use App\Filament\Pdv\Resources\Atributos\Tables\AtributosTable;
use App\Models\Atributo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AtributoResource extends Resource
{
    protected static ?string $model = Atributo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Atributos';

    protected static ?string $navigationGroup = 'Catálogo';

    protected static ?string $modelLabel = 'Atributo';

    protected static ?string $pluralModelLabel = 'Atributos';

    protected static ?string $recordTitleAttribute = 'Atributo';

    public static function form(Schema $schema): Schema
    {
        return AtributoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AtributosTable::configure($table);
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
            'index' => ListAtributos::route('/'),
            'create' => CreateAtributo::route('/create'),
            'edit' => EditAtributo::route('/{record}/edit'),
        ];
    }
}

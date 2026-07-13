<?php

namespace App\Filament\Pdv\Resources\Atributos;

use App\Filament\Pdv\Resources\Atributos\Pages\CreateAtributo;
use App\Filament\Pdv\Resources\Atributos\Pages\EditAtributo;
use App\Filament\Pdv\Resources\Atributos\Pages\ListAtributos;
use App\Filament\Pdv\Resources\Atributos\Schemas\AtributoForm;
use App\Filament\Pdv\Resources\Atributos\Tables\AtributosTable;
use App\Models\Atributo;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AtributoResource extends Resource
{
    protected static ?string $model = Atributo::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationLabel = 'Atributos';

    protected static string|UnitEnum|null $navigationGroup = 'Catálogo';
    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Atributo';

    protected static ?string $pluralModelLabel = 'Atributos';

    protected static ?string $recordTitleAttribute = 'Atributo';

    public static function canAccess(): bool              { return auth()->user()?->can('atributos.ver') ?? false; }
    public static function canCreate(): bool              { return auth()->user()?->can('atributos.crear') ?? false; }
    public static function canEdit(Model $record): bool   { return auth()->user()?->can('atributos.editar') ?? false; }
    public static function canDelete(Model $record): bool { return auth()->user()?->can('atributos.eliminar') ?? false; }

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

<?php

namespace App\Filament\Pdv\Resources\Marcas;

use App\Filament\Pdv\Resources\Marcas\Pages\CreateMarca;
use App\Filament\Pdv\Resources\Marcas\Pages\EditMarca;
use App\Filament\Pdv\Resources\Marcas\Pages\ListMarcas;
use App\Filament\Pdv\Resources\Marcas\Schemas\MarcaForm;
use App\Filament\Pdv\Resources\Marcas\Tables\MarcasTable;
use App\Models\Marca;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MarcaResource extends Resource
{
    protected static ?string $model = Marca::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationLabel = 'Marcas';

    protected static string|UnitEnum|null $navigationGroup = 'Catálogo';
    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Marca';

    protected static ?string $pluralModelLabel = 'Marcas';

    protected static ?string $recordTitleAttribute = 'Marca';

    public static function canAccess(): bool              { return Filament::getTenant()->tieneModulo('marcas') && (auth()->user()?->can('marcas.ver') ?? false); }
    public static function canCreate(): bool              { return auth()->user()?->can('marcas.crear') ?? false; }
    public static function canEdit(Model $record): bool   { return auth()->user()?->can('marcas.editar') ?? false; }
    public static function canDelete(Model $record): bool { return auth()->user()?->can('marcas.eliminar') ?? false; }

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

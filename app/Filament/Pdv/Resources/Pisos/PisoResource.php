<?php

namespace App\Filament\Pdv\Resources\Pisos;

use App\Filament\Pdv\Resources\Pisos\Pages\CreatePiso;
use App\Filament\Pdv\Resources\Pisos\Pages\EditPiso;
use App\Filament\Pdv\Resources\Pisos\Pages\ListPisos;
use App\Filament\Pdv\Resources\Pisos\RelationManagers\MesasRelationManager;
use App\Filament\Pdv\Resources\Pisos\Schemas\PisoForm;
use App\Filament\Pdv\Resources\Pisos\Tables\PisosTable;
use App\Models\Piso;
use BackedEnum;
use UnitEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PisoResource extends Resource
{
    protected static ?string $model = Piso::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Pisos y Mesas';

    protected static string|UnitEnum|null $navigationGroup = 'Restaurante';
    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Piso';

    protected static ?string $pluralModelLabel = 'Pisos y Mesas';

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function canAccess(): bool
    {
        return Filament::getTenant()->tieneModulo('mesas')
            && (auth()->user()?->can('mesas.ver') ?? false);
    }

    public static function canCreate(): bool              { return auth()->user()?->can('mesas.crear') ?? false; }
    public static function canEdit(Model $record): bool   { return auth()->user()?->can('mesas.editar') ?? false; }
    public static function canDelete(Model $record): bool { return auth()->user()?->can('mesas.eliminar') ?? false; }

    public static function form(Schema $schema): Schema
    {
        return PisoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PisosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MesasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListPisos::route('/'),
            'create' => CreatePiso::route('/create'),
            'edit'   => EditPiso::route('/{record}/edit'),
        ];
    }
}

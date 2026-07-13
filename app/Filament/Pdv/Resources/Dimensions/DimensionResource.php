<?php

namespace App\Filament\Pdv\Resources\Dimensions;

use App\Filament\Pdv\Resources\Dimensions\Pages\CreateDimension;
use App\Filament\Pdv\Resources\Dimensions\Pages\EditDimension;
use App\Filament\Pdv\Resources\Dimensions\Pages\ListDimensions;
use App\Filament\Pdv\Resources\Dimensions\RelationManagers\UnidadesMedidaRelationManager;
use App\Filament\Pdv\Resources\Dimensions\Schemas\DimensionForm;
use App\Filament\Pdv\Resources\Dimensions\Tables\DimensionsTable;
use App\Models\Dimension;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DimensionResource extends Resource
{
    protected static ?string $model = Dimension::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsPointingOut;

    protected static ?string $navigationLabel = 'Dimensiones';

    protected static string|UnitEnum|null $navigationGroup = 'Catálogo';
    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Dimensión';

    protected static ?string $pluralModelLabel = 'Dimensiones';

    protected static ?string $recordTitleAttribute = 'Dimension';

    public static function canAccess(): bool              { return auth()->user()?->can('dimensiones.ver') ?? false; }
    public static function canCreate(): bool              { return auth()->user()?->can('dimensiones.crear') ?? false; }
    public static function canEdit(Model $record): bool   { return auth()->user()?->can('dimensiones.editar') ?? false; }
    public static function canDelete(Model $record): bool { return auth()->user()?->can('dimensiones.eliminar') ?? false; }

    public static function form(Schema $schema): Schema
    {
        return DimensionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DimensionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            UnidadesMedidaRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDimensions::route('/'),
            'create' => CreateDimension::route('/create'),
            'edit' => EditDimension::route('/{record}/edit'),
        ];
    }
}

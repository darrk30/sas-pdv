<?php

namespace App\Filament\Pdv\Resources\Produccions;

use App\Filament\Pdv\Resources\Produccions\Pages\CreateProduccion;
use App\Filament\Pdv\Resources\Produccions\Pages\EditProduccion;
use App\Filament\Pdv\Resources\Produccions\Pages\ListProduccions;
use App\Filament\Pdv\Resources\Produccions\Schemas\ProduccionForm;
use App\Filament\Pdv\Resources\Produccions\Tables\ProduccionsTable;
use App\Models\Produccion;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProduccionResource extends Resource
{
    protected static ?string $model = Produccion::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationLabel = 'Producción';

    protected static string|UnitEnum|null $navigationGroup = 'Catálogo';
    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Producción';

    protected static ?string $pluralModelLabel = 'Producciones';

    protected static ?string $recordTitleAttribute = 'Produccion';

    public static function canAccess(): bool              { return auth()->user()?->can('produccion.ver') ?? false; }
    public static function canCreate(): bool              { return auth()->user()?->can('produccion.crear') ?? false; }
    public static function canEdit(Model $record): bool   { return auth()->user()?->can('produccion.editar') ?? false; }
    public static function canDelete(Model $record): bool { return auth()->user()?->can('produccion.eliminar') ?? false; }

    public static function form(Schema $schema): Schema
    {
        return ProduccionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProduccionsTable::configure($table);
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
            'index' => ListProduccions::route('/'),
            'create' => CreateProduccion::route('/create'),
            'edit' => EditProduccion::route('/{record}/edit'),
        ];
    }
}

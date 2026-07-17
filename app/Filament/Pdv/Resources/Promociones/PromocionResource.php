<?php

namespace App\Filament\Pdv\Resources\Promociones;

use App\Filament\Pdv\Resources\Promociones\Pages\CreatePromocion;
use App\Filament\Pdv\Resources\Promociones\Pages\EditPromocion;
use App\Filament\Pdv\Resources\Promociones\Pages\ListPromociones;
use App\Filament\Pdv\Resources\Promociones\Schemas\PromocionForm;
use App\Filament\Pdv\Resources\Promociones\Tables\PromocionesTable;
use App\Models\Promocion;
use BackedEnum;
use UnitEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PromocionResource extends Resource
{
    protected static ?string $model = Promocion::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Promociones';

    protected static string|UnitEnum|null $navigationGroup = 'Pedidos Web';
    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Promoción';

    protected static ?string $pluralModelLabel = 'Promociones';

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function canAccess(): bool              { return Filament::getTenant()->tieneModulo('promociones') && (auth()->user()?->can('promociones.ver') ?? false); }
    public static function canCreate(): bool              { return auth()->user()?->can('promociones.crear') ?? false; }
    public static function canEdit(Model $record): bool   { return auth()->user()?->can('promociones.editar') ?? false; }
    public static function canDelete(Model $record): bool { return auth()->user()?->can('promociones.eliminar') ?? false; }

    public static function form(Schema $schema): Schema
    {
        return PromocionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PromocionesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListPromociones::route('/'),
            'create' => CreatePromocion::route('/create'),
            'edit'   => EditPromocion::route('/{record}/edit'),
        ];
    }
}

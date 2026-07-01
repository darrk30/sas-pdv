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
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PromocionResource extends Resource
{
    protected static ?string $model = Promocion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Promociones';

    protected static string|UnitEnum|null $navigationGroup = 'Productos';
    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Promoción';

    protected static ?string $pluralModelLabel = 'Promociones';

    protected static ?string $recordTitleAttribute = 'nombre';

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

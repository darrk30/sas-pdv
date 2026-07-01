<?php

namespace App\Filament\Pdv\Resources\Ajustes;

use App\Filament\Pdv\Resources\Ajustes\Pages\CreateAjuste;
use App\Filament\Pdv\Resources\Ajustes\Pages\EditAjuste;
use App\Filament\Pdv\Resources\Ajustes\Pages\ListAjustes;
use App\Filament\Pdv\Resources\Ajustes\Pages\ViewAjuste;
use App\Filament\Pdv\Resources\Ajustes\Schemas\AjusteForm;
use App\Filament\Pdv\Resources\Ajustes\Tables\AjustesTable;
use App\Models\Ajuste;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AjusteResource extends Resource
{
    protected static ?string $model = Ajuste::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Ajustes de Stock';

    protected static string|UnitEnum|null $navigationGroup = 'Productos';
    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Ajuste';

    protected static ?string $pluralModelLabel = 'Ajustes';

    protected static ?string $recordTitleAttribute = 'codigo';

    public static function form(Schema $schema): Schema
    {
        return AjusteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AjustesTable::configure($table)
            ->recordUrl(fn(Ajuste $record): string =>
                $record->estado === 'borrador'
                    ? static::getUrl('edit', ['record' => $record])
                    : static::getUrl('view', ['record' => $record])
            );
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListAjustes::route('/'),
            'create' => CreateAjuste::route('/create'),
            'edit'   => EditAjuste::route('/{record}/edit'),
            'view'   => ViewAjuste::route('/{record}'),
        ];
    }
}

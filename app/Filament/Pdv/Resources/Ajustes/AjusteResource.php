<?php

namespace App\Filament\Pdv\Resources\Ajustes;

use App\Filament\Pdv\Resources\Ajustes\Pages\CreateAjuste;
use App\Filament\Pdv\Resources\Ajustes\Pages\EditAjuste;
use App\Filament\Pdv\Resources\Ajustes\Pages\ListAjustes;
use App\Filament\Pdv\Resources\Ajustes\Schemas\AjusteForm;
use App\Filament\Pdv\Resources\Ajustes\Tables\AjustesTable;
use App\Models\Ajuste;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AjusteResource extends Resource
{
    protected static ?string $model = Ajuste::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Ajuste';

    public static function form(Schema $schema): Schema
    {
        return AjusteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AjustesTable::configure($table);
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
            'index' => ListAjustes::route('/'),
            'create' => CreateAjuste::route('/create'),
            'edit' => EditAjuste::route('/{record}/edit'),
        ];
    }
}

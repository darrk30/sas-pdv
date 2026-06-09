<?php

namespace App\Filament\Pdv\Resources\Produccions;

use App\Filament\Pdv\Resources\Produccions\Pages\CreateProduccion;
use App\Filament\Pdv\Resources\Produccions\Pages\EditProduccion;
use App\Filament\Pdv\Resources\Produccions\Pages\ListProduccions;
use App\Filament\Pdv\Resources\Produccions\Schemas\ProduccionForm;
use App\Filament\Pdv\Resources\Produccions\Tables\ProduccionsTable;
use App\Models\Produccion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProduccionResource extends Resource
{
    protected static ?string $model = Produccion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Produccion';

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

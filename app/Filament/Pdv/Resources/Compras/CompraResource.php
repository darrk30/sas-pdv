<?php

namespace App\Filament\Pdv\Resources\Compras;

use App\Filament\Pdv\Resources\Compras\Pages\CreateCompra;
use App\Filament\Pdv\Resources\Compras\Pages\EditCompra;
use App\Filament\Pdv\Resources\Compras\Pages\ListCompras;
use App\Filament\Pdv\Resources\Compras\Schemas\CompraForm;
use App\Filament\Pdv\Resources\Compras\Tables\ComprasTable;
use App\Models\Compra;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CompraResource extends Resource
{
    protected static ?string $model = Compra::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $navigationLabel = 'Compras';

    protected static ?string $navigationGroup = 'Compras';

    protected static ?string $modelLabel = 'Compra';

    protected static ?string $pluralModelLabel = 'Compras';

    protected static ?string $recordTitleAttribute = 'codigo';

    public static function form(Schema $schema): Schema
    {
        return CompraForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ComprasTable::configure($table)
            ->recordUrl(fn(Compra $record): ?string =>
                $record->esBorrador()
                    ? static::getUrl('edit', ['record' => $record])
                    : null
            );
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListCompras::route('/'),
            'create' => CreateCompra::route('/create'),
            'edit'   => EditCompra::route('/{record}/edit'),
        ];
    }
}

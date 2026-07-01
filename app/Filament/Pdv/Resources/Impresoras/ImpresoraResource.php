<?php

namespace App\Filament\Pdv\Resources\Impresoras;

use App\Filament\Pdv\Resources\Impresoras\Pages\CreateImpresora;
use App\Filament\Pdv\Resources\Impresoras\Pages\EditImpresora;
use App\Filament\Pdv\Resources\Impresoras\Pages\ListImpresoras;
use App\Filament\Pdv\Resources\Impresoras\Schemas\ImpresoraForm;
use App\Filament\Pdv\Resources\Impresoras\Tables\ImpresorasTable;
use App\Models\Impresora;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ImpresoraResource extends Resource
{
    protected static ?string $model = Impresora::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPrinter;

    protected static ?string $navigationLabel = 'Impresoras';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Impresora';

    protected static ?string $pluralModelLabel = 'Impresoras';

    protected static ?string $recordTitleAttribute = 'Impresora';

    public static function form(Schema $schema): Schema
    {
        return ImpresoraForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ImpresorasTable::configure($table);
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
            'index' => ListImpresoras::route('/'),
            'create' => CreateImpresora::route('/create'),
            'edit' => EditImpresora::route('/{record}/edit'),
        ];
    }
}

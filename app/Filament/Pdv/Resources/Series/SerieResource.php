<?php

namespace App\Filament\Pdv\Resources\Series;

use App\Filament\Pdv\Resources\Series\Pages\CreateSerie;
use App\Filament\Pdv\Resources\Series\Pages\EditSerie;
use App\Filament\Pdv\Resources\Series\Pages\ListSeries;
use App\Filament\Pdv\Resources\Series\Schemas\SerieForm;
use App\Filament\Pdv\Resources\Series\Tables\SeriesTable;
use App\Models\Serie;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SerieResource extends Resource
{
    protected static ?string $model = Serie::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $navigationLabel = 'Series';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Serie';

    protected static ?string $pluralModelLabel = 'Series';

    protected static ?string $recordTitleAttribute = 'serie';

    public static function form(Schema $schema): Schema
    {
        return SerieForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SeriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListSeries::route('/'),
            'create' => CreateSerie::route('/create'),
            'edit'   => EditSerie::route('/{record}/edit'),
        ];
    }
}

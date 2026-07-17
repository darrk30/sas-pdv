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
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SerieResource extends Resource
{
    protected static ?string $model = Serie::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-hashtag';

    protected static ?string $navigationLabel = 'Series';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Serie';

    protected static ?string $pluralModelLabel = 'Series';

    protected static ?string $recordTitleAttribute = 'serie';

    public static function canAccess(): bool              { return Filament::getTenant()->tieneModulo('series') && (auth()->user()?->can('series.ver') ?? false); }
    public static function canCreate(): bool              { return auth()->user()?->can('series.crear') ?? false; }
    public static function canEdit(Model $record): bool   { return auth()->user()?->can('series.editar') ?? false; }
    public static function canDelete(Model $record): bool { return auth()->user()?->can('series.eliminar') ?? false; }

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

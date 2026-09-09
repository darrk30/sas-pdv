<?php

namespace App\Filament\Pdv\Resources\Comandas;

use App\Filament\Pdv\Resources\Comandas\Pages\EditComanda;
use App\Filament\Pdv\Resources\Comandas\Pages\ListComandas;
use App\Enums\TipoOrigenOrden;
use App\Models\Orden;
use BackedEnum;
use UnitEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ComandaResource extends Resource
{
    protected static ?string $model = Orden::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Comandas';

    protected static string|UnitEnum|null $navigationGroup = 'Restaurante';
    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Comanda';

    protected static ?string $pluralModelLabel = 'Comandas';

    protected static ?string $slug = 'comandas';

    public static function canAccess(): bool
    {
        return Filament::getTenant()->tieneModulo('comandas')
            && (auth()->user()?->can('comandas.ver') ?? false);
    }

    public static function canCreate(): bool              { return false; }
    public static function canEdit(Model $record): bool   { return auth()->user()?->can('comandas.gestionar') ?? false; }
    public static function canDelete(Model $record): bool { return false; }

    // Solo ordenes de tipo restaurante
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tipo_origen', TipoOrigenOrden::Restaurante);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComandas::route('/'),
            'edit'  => EditComanda::route('/{record}/edit'),
        ];
    }
}

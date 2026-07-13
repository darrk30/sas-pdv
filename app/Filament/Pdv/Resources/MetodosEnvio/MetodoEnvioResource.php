<?php

namespace App\Filament\Pdv\Resources\MetodosEnvio;

use App\Filament\Pdv\Resources\MetodosEnvio\Pages\CreateMetodoEnvio;
use App\Filament\Pdv\Resources\MetodosEnvio\Pages\EditMetodoEnvio;
use App\Filament\Pdv\Resources\MetodosEnvio\Pages\ListMetodosEnvio;
use App\Filament\Pdv\Resources\MetodosEnvio\Schemas\MetodoEnvioForm;
use App\Filament\Pdv\Resources\MetodosEnvio\Tables\MetodosEnvioTable;
use App\Models\MetodoEnvio;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MetodoEnvioResource extends Resource
{
    protected static ?string $model = MetodoEnvio::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'Métodos de envío';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Método de envío';

    protected static ?string $pluralModelLabel = 'Métodos de envío';

    protected static ?string $slug = 'metodos-envio';

    public static function canAccess(): bool              { return auth()->user()?->can('metodos_envio.ver') ?? false; }
    public static function canCreate(): bool              { return auth()->user()?->can('metodos_envio.crear') ?? false; }
    public static function canEdit(Model $record): bool   { return auth()->user()?->can('metodos_envio.editar') ?? false; }
    public static function canDelete(Model $record): bool { return auth()->user()?->can('metodos_envio.eliminar') ?? false; }

    public static function form(Schema $schema): Schema
    {
        return MetodoEnvioForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MetodosEnvioTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListMetodosEnvio::route('/'),
            'create' => CreateMetodoEnvio::route('/create'),
            'edit'   => EditMetodoEnvio::route('/{record}/edit'),
        ];
    }
}

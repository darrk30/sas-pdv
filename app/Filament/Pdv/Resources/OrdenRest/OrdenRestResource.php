<?php

namespace App\Filament\Pdv\Resources\OrdenRest;

use App\Enums\TipoOrigenOrden;
use App\Filament\Pdv\Pages\MapaMesasPage;
use App\Filament\Pdv\Resources\OrdenRest\Pages\CobrarPedido;
use App\Filament\Pdv\Resources\OrdenRest\Pages\EditPedido;
use App\Filament\Pdv\Resources\OrdenRest\Pages\NuevoPedido;
use App\Models\Orden;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class OrdenRestResource extends Resource
{
    protected static ?string $model = Orden::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Pedidos';

    protected static string|UnitEnum|null $navigationGroup = 'Restaurante';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Pedido';

    protected static ?string $pluralModelLabel = 'Pedidos';

    protected static ?string $slug = 'orden-rest';

    public static function canAccess(): bool
    {
        return Filament::getTenant()->tieneModulo('restaurante')
            && (auth()->user()?->can('restaurante.ver') ?? false);
    }

    public static function canCreate(): bool             { return auth()->user()?->can('restaurante.pedido.crear') ?? false; }
    public static function canEdit(Model $record): bool  { return auth()->user()?->can('restaurante.pedido.editar') ?? false; }
    public static function canDelete(Model $record): bool{ return auth()->user()?->can('restaurante.pedido.eliminar') ?? false; }

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

    public static function getIndexUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?\Illuminate\Database\Eloquent\Model $tenant = null, bool $shouldGuessMissingParameters = false): string
    {
        return MapaMesasPage::getUrl(tenant: $tenant ?? Filament::getTenant());
    }

    public static function getPages(): array
    {
        return [
            'create' => NuevoPedido::route('/create'),
            'edit'   => EditPedido::route('/{record}/edit'),
            'cobrar' => CobrarPedido::route('/{record}/cobrar'),
        ];
    }
}

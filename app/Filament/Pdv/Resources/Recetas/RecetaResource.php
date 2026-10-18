<?php

namespace App\Filament\Pdv\Resources\Recetas;

use App\Filament\Pdv\Resources\Recetas\Pages\CreateReceta;
use App\Filament\Pdv\Resources\Recetas\Pages\EditReceta;
use App\Filament\Pdv\Resources\Recetas\Pages\ListRecetas;
use App\Models\AjusteDetalle;
use App\Models\Producto;
use App\Models\Receta;
use App\Models\UnidadesMedida;
use App\Models\Variante;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RecetaResource extends Resource
{
    protected static ?string $model = Receta::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static ?string $navigationLabel = 'Recetas';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventario';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Receta';

    protected static ?string $pluralModelLabel = 'Recetas';

    public static function canAccess(): bool         { return Filament::getTenant()->tieneModulo('recetas') && (auth()->user()?->can('recetas.ver') ?? false); }
    public static function canCreate(): bool         { return auth()->user()?->can('recetas.crear') ?? false; }
    public static function canEdit(Model $r): bool   { return auth()->user()?->can('recetas.editar') ?? false; }
    public static function canDelete(Model $r): bool { return auth()->user()?->can('recetas.eliminar') ?? false; }

    // ── Formulario ────────────────────────────────────────────────────────────

    public static function form(Schema $schema): Schema
    {
        $empresaId = Filament::getTenant()?->id;

        return $schema->components([

            // ── Select unificado: producto / variante objetivo de la receta ────
            Select::make('item_id')
                ->label('Producto / Variante con receta')
                ->placeholder('Buscar por nombre o código…')
                ->searchable()
                ->required()
                ->live()
                ->dehydrated(false)
                ->afterStateHydrated(function ($component, $state, $record) {
                    // Al editar: precarga el valor compuesto desde el registro
                    if ($state === null && $record) {
                        $val = $record->variante_id
                            ? "variante_{$record->variante_id}"
                            : ($record->producto_id ? "producto_{$record->producto_id}" : null);
                        $component->state($val);
                    }
                })
                ->options(fn () => self::_buscarItems('', $empresaId))
                ->getSearchResultsUsing(fn (string $search) => self::_buscarItems($search, $empresaId))
                ->getOptionLabelUsing(fn ($value) => self::_etiquetaItem($value))
                ->afterStateUpdated(function (?string $state, Set $set) {
                    // Solo actúa cuando el usuario elige algo (estado no nulo)
                    if (blank($state)) return;
                    [$tipo, $id] = explode('_', $state, 2);
                    if ($tipo === 'variante') {
                        $set('variante_id', (int) $id);
                        $set('producto_id', null);
                    } else {
                        $set('producto_id', (int) $id);
                        $set('variante_id', null);
                    }
                })
                ->columnSpanFull(),

            Hidden::make('producto_id'),
            Hidden::make('variante_id'),

            TextInput::make('nombre')
                ->label('Nombre de la receta')
                ->placeholder('Ej: Perfume Acqua 50ml')
                ->required()
                ->maxLength(200)
                ->columnSpanFull(),

            Placeholder::make('costo_info')
                ->label('Costo calculado')
                ->content(fn ($record) => $record
                    ? 'S/ ' . number_format($record->costoCalculado(), 4) . ' — Se actualiza al guardar'
                    : 'Se calculará al guardar la receta'
                )
                ->columnSpanFull(),

            // ── Insumos ───────────────────────────────────────────────────────
            Repeater::make('detalles')
                ->label('Insumos')
                ->relationship('detalles')
                ->table([
                    TableColumn::make('Insumo')->width('55%'),
                    TableColumn::make('Cantidad')->width('15%'),
                    TableColumn::make('Unidad')->width('25%'),
                ])
                ->schema([
                    Select::make('insumo_item_id')
                        ->label('Insumo')
                        ->placeholder('Buscar insumo…')
                        ->searchable()
                        ->required()
                        ->live()
                        ->dehydrated(false)
                        ->formatStateUsing(fn ($record) => $record?->insumo_variante_id
                            ? "variante_{$record->insumo_variante_id}"
                            : ($record?->insumo_producto_id ? "producto_{$record->insumo_producto_id}" : null)
                        )
                        ->options(fn () => self::_buscarItems('', $empresaId))
                        ->getSearchResultsUsing(fn (string $search) => self::_buscarItems($search, $empresaId))
                        ->getOptionLabelUsing(fn ($value) => self::_etiquetaItem($value))
                        ->afterStateUpdated(function (?string $state, Set $set) {
                            if (blank($state)) return;
                            [$tipo, $id] = explode('_', $state, 2);

                            $u = null;
                            if ($tipo === 'variante') {
                                $set('insumo_variante_id', (int) $id);
                                $set('insumo_producto_id', null);
                                $v = Variante::with('producto.unidadMedida')->find($id);
                                $u = $v?->producto?->unidadMedida;
                            } else {
                                $set('insumo_producto_id', (int) $id);
                                $set('insumo_variante_id', null);
                                $p = Producto::with('unidadMedida')->find($id);
                                $u = $p?->unidadMedida;
                            }

                            $set('unidad_medida_id', $u?->id);
                            $set('_dimension_id', $u?->dimension_id);
                        }),

                    Hidden::make('insumo_producto_id'),
                    Hidden::make('insumo_variante_id'),

                    TextInput::make('cantidad')
                        ->label('Cantidad')
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->step('any'),

                    // Campo virtual para filtrar unidades — se define ANTES del select
                    Hidden::make('_dimension_id')
                        ->dehydrated(false)
                        ->formatStateUsing(fn ($record) => $record?->unidad_medida_id
                            ? UnidadesMedida::find($record->unidad_medida_id)?->dimension_id
                            : null
                        ),

                    Select::make('unidad_medida_id')
                        ->label('Unidad')
                        ->live()
                        ->options(function (Get $get) use ($empresaId): array {
                            $dimId = $get('_dimension_id');
                            return UnidadesMedida::where('empresa_id', $empresaId)
                                ->when($dimId, fn ($q) => $q->where('dimension_id', $dimId))
                                ->orderBy('nombre')
                                ->pluck('nombre', 'id')
                                ->toArray();
                        })
                        ->searchable(),
                ])
                ->addActionLabel('+ Agregar insumo')
                ->reorderable(false)
                ->columnSpanFull(),
        ]);
    }

    // ── Tabla ─────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->query(
                Receta::where('empresa_id', Filament::getTenant()->id)
                    ->with([
                        'producto',
                        'variante.producto',
                        'variante.valores.valor',
                        'detalles.unidadMedida',
                        'detalles.insumoProducto.unidadMedida',
                        'detalles.insumoVariante.producto.unidadMedida',
                    ])
            )
            ->columns([
                TextColumn::make('nombre')
                    ->label('Receta')
                    ->searchable()
                    ->description(fn (Receta $r): string =>
                        $r->variante_id
                            ? ($r->variante?->valores->map(fn ($p) => $p->valor?->nombre)->filter()->implode(' / ') ?? '—')
                            : 'Producto simple'
                    ),

                TextColumn::make('producto_display')
                    ->label('Producto')
                    ->state(fn (Receta $r): string =>
                        $r->producto?->nombre
                        ?? $r->variante?->producto?->nombre
                        ?? '—'
                    ),

                TextColumn::make('detalles_count')
                    ->label('Insumos')
                    ->state(fn (Receta $r): int => $r->detalles->count())
                    ->badge()
                    ->color('gray'),

                TextColumn::make('costo_calculado')
                    ->label('Costo calculado')
                    ->state(fn (Receta $r): string => 'S/ ' . number_format($r->costoCalculado(), 4))
                    ->color('success'),

                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('nombre');
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListRecetas::route('/'),
            'create' => CreateReceta::route('/create'),
            'edit'   => EditReceta::route('/{record}/edit'),
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Etiqueta para un valor compuesto "tipo_id". */
    private static function _etiquetaItem(?string $value): ?string
    {
        if (blank($value)) return null;
        [$tipo, $id] = explode('_', $value, 2);
        if ($tipo === 'variante') {
            $v = Variante::with(['producto', 'valores.valor'])->find($id);
            if (! $v) return null;
            $nombre = AjusteDetalle::generarNombre(null, $v);
            return $v->codigo ? "[{$v->codigo}] {$nombre}" : $nombre;
        }
        $p   = Producto::find($id);
        $tag = $p?->codigo_interno ?: $p?->codigo_barras;
        return $p ? ($tag ? "[{$tag}] {$p->nombre}" : $p->nombre) : null;
    }

    /** Busca productos simples y variantes, devuelve ["tipo_id" => "etiqueta"]. */
    private static function _buscarItems(string $search, ?int $empresaId): array
    {
        $opciones  = [];
        $esPreload = blank($search);
        $limite    = $esPreload ? 50 : 25;

        $simples = Producto::query()
            ->doesntHave('variantesActivas')
            ->whereIn('estado', ['activo', 'inactivo'])
            ->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
            ->when(! $esPreload, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('codigo_interno', 'like', "%{$search}%")
                  ->orWhere('codigo_barras', 'like', "%{$search}%");
            }))
            ->orderBy('nombre')
            ->limit($limite)
            ->get();

        foreach ($simples as $p) {
            $tag   = $p->codigo_interno ?: $p->codigo_barras;
            $label = $tag ? "[{$tag}] {$p->nombre}" : $p->nombre;
            if ($p->estado === 'inactivo') $label .= ' (inactivo)';
            $opciones["producto_{$p->id}"] = $label;
        }

        $variantes = Variante::query()
            ->with(['producto', 'valores.valor', 'valores.productoAtributo.atributo'])
            ->join('productos as p_ord', 'p_ord.id', '=', 'variantes.producto_id')
            ->select('variantes.*')
            ->whereIn('variantes.estado', ['activo', 'inactivo'])
            ->whereIn('p_ord.estado', ['activo', 'inactivo'])
            ->when($empresaId, fn ($q) => $q->where('p_ord.empresa_id', $empresaId))
            ->when(! $esPreload, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('variantes.codigo', 'like', "%{$search}%")
                  ->orWhere('p_ord.nombre', 'like', "%{$search}%")
                  ->orWhere('p_ord.codigo_interno', 'like', "%{$search}%")
                  ->orWhere('p_ord.codigo_barras', 'like', "%{$search}%");
            }))
            ->orderBy('p_ord.nombre')
            ->limit($limite)
            ->get();

        foreach ($variantes as $v) {
            $nombre = AjusteDetalle::generarNombre(null, $v);
            $label  = $v->codigo ? "[{$v->codigo}] {$nombre}" : $nombre;
            if ($v->estado === 'inactivo') $label .= ' (inactivo)';
            $opciones["variante_{$v->id}"] = $label;
        }

        return $opciones;
    }
}

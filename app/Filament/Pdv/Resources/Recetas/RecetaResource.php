<?php

namespace App\Filament\Pdv\Resources\Recetas;

use App\Filament\Pdv\Resources\Recetas\Pages\CreateReceta;
use App\Filament\Pdv\Resources\Recetas\Pages\EditReceta;
use App\Filament\Pdv\Resources\Recetas\Pages\ListRecetas;
use App\Models\Producto;
use App\Models\Receta;
use App\Models\Variante;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
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

    public static function canAccess(): bool            { return auth()->user()?->can('productos.ver') ?? false; }
    public static function canCreate(): bool            { return auth()->user()?->can('productos.crear') ?? false; }
    public static function canEdit(Model $r): bool      { return auth()->user()?->can('productos.crear') ?? false; }
    public static function canDelete(Model $r): bool    { return auth()->user()?->can('productos.crear') ?? false; }

    public static function form(Schema $schema): Schema
    {
        $empresaId = Filament::getTenant()?->id;

        return $schema->components([

            Select::make('producto_id')
                ->label('Producto')
                ->placeholder('Selecciona un producto…')
                ->searchable()
                ->options(fn () => Producto::where('empresa_id', $empresaId)
                    ->where('estado', 'activo')
                    ->orderBy('nombre')
                    ->pluck('nombre', 'id')
                    ->toArray())
                ->live()
                ->required()
                ->afterStateUpdated(fn ($set) => $set('variante_id', null)),

            Select::make('variante_id')
                ->label('Variante (si aplica)')
                ->placeholder('Aplica a todas las variantes')
                ->searchable()
                ->options(fn (Get $get) => $get('producto_id')
                    ? Variante::where('producto_id', $get('producto_id'))
                        ->where('estado', 'activo')
                        ->with('valores.valor')
                        ->get()
                        ->mapWithKeys(function ($v) {
                            $vals = $v->valores->map(fn ($pav) => $pav->valor?->nombre)->filter()->implode(' / ');
                            return [$v->id => $vals ?: "Variante #{$v->id}"];
                        })
                        ->toArray()
                    : [])
                ->live()
                ->hidden(fn (Get $get) => ! $get('producto_id'))
                ->helperText('Deja vacío para que la receta aplique al producto simple (sin variantes).'),

            TextInput::make('nombre')
                ->label('Nombre de la receta')
                ->placeholder('Ej: Perfume 50ml base alcohol')
                ->required()
                ->maxLength(200)
                ->columnSpanFull(),

            Repeater::make('detalles')
                ->label('Insumos')
                ->relationship('detalles')
                ->schema([
                    Select::make('insumo_producto_id')
                        ->label('Insumo (producto)')
                        ->placeholder('Selecciona el insumo…')
                        ->searchable()
                        ->options(fn () => Producto::where('empresa_id', $empresaId)
                            ->where('estado', 'activo')
                            ->orderBy('nombre')
                            ->pluck('nombre', 'id')
                            ->toArray())
                        ->live()
                        ->required()
                        ->afterStateUpdated(fn ($set) => $set('insumo_variante_id', null))
                        ->columnSpan(2),

                    Select::make('insumo_variante_id')
                        ->label('Variante del insumo')
                        ->placeholder('Sin variante')
                        ->searchable()
                        ->options(fn (Get $get) => $get('insumo_producto_id')
                            ? Variante::where('producto_id', $get('insumo_producto_id'))
                                ->where('estado', 'activo')
                                ->with('valores.valor')
                                ->get()
                                ->mapWithKeys(function ($v) {
                                    $vals = $v->valores->map(fn ($pav) => $pav->valor?->nombre)->filter()->implode(' / ');
                                    return [$v->id => $vals ?: "Variante #{$v->id}"];
                                })
                                ->toArray()
                            : [])
                        ->hidden(fn (Get $get) => ! $get('insumo_producto_id'))
                        ->columnSpan(2),

                    TextInput::make('cantidad')
                        ->label('Cantidad')
                        ->numeric()
                        ->minValue(0.0001)
                        ->required()
                        ->step(0.001)
                        ->columnSpan(1),

                    TextInput::make('unidad')
                        ->label('Unidad')
                        ->placeholder('g, ml, unidad…')
                        ->maxLength(50)
                        ->columnSpan(1),
                ])
                ->columns(6)
                ->addActionLabel('+ Agregar insumo')
                ->reorderable(false)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(
                Receta::where('empresa_id', Filament::getTenant()->id)
                    ->with(['producto', 'variante.valores.valor', 'detalles'])
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

                TextColumn::make('producto.nombre')
                    ->label('Producto')
                    ->searchable(),

                TextColumn::make('detalles_count')
                    ->label('Insumos')
                    ->state(fn (Receta $r): int => $r->detalles->count())
                    ->badge()
                    ->color('gray'),

                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
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
}

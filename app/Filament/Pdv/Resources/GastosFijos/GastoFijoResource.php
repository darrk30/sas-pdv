<?php

namespace App\Filament\Pdv\Resources\GastosFijos;

use App\Enums\FrecuenciaGasto;
use App\Filament\Pdv\Resources\GastosFijos\Pages\CreateGastoFijo;
use App\Filament\Pdv\Resources\GastosFijos\Pages\EditGastoFijo;
use App\Filament\Pdv\Resources\GastosFijos\Pages\ListGastosFijos;
use App\Enums\CategoriaGasto;
use App\Models\Gasto;
use App\Models\GastoFijo;
use App\Models\MetodoPago;
use BackedEnum;
use UnitEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class GastoFijoResource extends Resource
{
    protected static ?string $model = GastoFijo::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Gastos Fijos';

    protected static string|UnitEnum|null $navigationGroup = 'Gastos';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Gasto fijo';

    protected static ?string $pluralModelLabel = 'Gastos fijos';

    protected static ?string $recordTitleAttribute = 'nombre';

    protected static ?string $slug = 'gastos-fijos';

    public static function canAccess(): bool        { return Filament::getTenant()->tieneModulo('gastos') && (auth()->user()?->can('gastos_fijos.ver') ?? false); }
    public static function canCreate(): bool        { return auth()->user()?->can('gastos_fijos.crear') ?? false; }
    public static function canEdit(Model $r): bool  { return auth()->user()?->can('gastos_fijos.editar') ?? false; }
    public static function canDelete(Model $r): bool { return auth()->user()?->can('gastos_fijos.eliminar') ?? false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('nombre')
                ->label('Nombre del gasto')
                ->placeholder('Ej: Alquiler local, Internet, Planilla...')
                ->required()
                ->maxLength(120)
                ->columnSpanFull(),

            Forms\Components\TextInput::make('monto')
                ->label('Monto (S/)')
                ->numeric()
                ->prefix('S/')
                ->minValue(0.01)
                ->required(),

            Forms\Components\Select::make('frecuencia')
                ->label('Frecuencia')
                ->options(collect(FrecuenciaGasto::cases())->mapWithKeys(fn ($c) => [$c->value => $c->getLabel()]))
                ->required()
                ->default('mensual'),

            Forms\Components\Select::make('estado')
                ->label('Estado')
                ->options(['activo' => 'Activo', 'inactivo' => 'Inactivo'])
                ->required()
                ->default('activo'),

            Forms\Components\DatePicker::make('proxima_fecha_pago')
                ->label('Próxima fecha de pago')
                ->helperText('Cuándo se debe pagar por primera vez. Después se avanza automáticamente.')
                ->nullable()
                ->native(false),

            Forms\Components\TextInput::make('dias_aviso')
                ->label('Días de aviso previo')
                ->numeric()
                ->default(3)
                ->minValue(1)
                ->maxValue(30)
                ->suffix('días')
                ->helperText('Con cuántos días de anticipación notificar.'),

            Forms\Components\Textarea::make('notas')
                ->label('Notas')
                ->placeholder('Observaciones, proveedor...')
                ->rows(3)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Gasto')
                    ->searchable()
                    ->weight('medium'),

                TextColumn::make('monto')
                    ->label('Monto')
                    ->money('PEN')
                    ->sortable(),

                TextColumn::make('frecuencia')
                    ->label('Frecuencia')
                    ->badge()
                    ->sortable(),

                TextColumn::make('monto_mensual')
                    ->label('Equiv. mensual')
                    ->getStateUsing(fn (GastoFijo $r) => 'S/ ' . number_format(
                        $r->frecuencia->montoEnPeriodo((float) $r->monto, FrecuenciaGasto::Mensual),
                        2
                    ))
                    ->color('gray'),

                TextColumn::make('proxima_fecha_pago')
                    ->label('Próx. pago')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn (GastoFijo $r) => match (true) {
                        $r->proxima_fecha_pago === null                                   => 'gray',
                        $r->proxima_fecha_pago->isPast()                                  => 'danger',
                        $r->proxima_fecha_pago->lte(now()->addDays($r->dias_aviso ?? 3))  => 'warning',
                        default                                                            => 'success',
                    })
                    ->description(fn (GastoFijo $r) => match (true) {
                        $r->proxima_fecha_pago === null        => null,
                        $r->proxima_fecha_pago->isToday()      => 'Vence hoy',
                        $r->proxima_fecha_pago->isTomorrow()   => 'Vence mañana',
                        $r->proxima_fecha_pago->isPast()       => 'Vencido hace ' . (int) now()->startOfDay()->diffInDays($r->proxima_fecha_pago->startOfDay()) . ' días',
                        default                                => 'En ' . (int) now()->startOfDay()->diffInDays($r->proxima_fecha_pago->startOfDay()) . ' días',
                    }),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'activo'   => 'success',
                        'inactivo' => 'danger',
                        default    => 'gray',
                    }),

                TextColumn::make('notas')
                    ->label('Notas')
                    ->limit(40)
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('frecuencia')
                    ->label('Frecuencia')
                    ->options(collect(FrecuenciaGasto::cases())->mapWithKeys(fn ($c) => [$c->value => $c->getLabel()])),

                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(['activo' => 'Activo', 'inactivo' => 'Inactivo']),
            ])
            ->actions([
                Action::make('registrar_pago')
                    ->label('Pagar')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (GastoFijo $r) => $r->estaActivo() && $r->proxima_fecha_pago !== null)
                    ->modalHeading(fn (GastoFijo $r) => 'Registrar pago — ' . $r->nombre)
                    ->modalWidth('md')
                    ->form(fn (GastoFijo $r) => [
                        Forms\Components\TextInput::make('monto')
                            ->label('Monto a pagar (S/)')
                            ->numeric()
                            ->prefix('S/')
                            ->default(fn () => (float) $r->monto)
                            ->minValue(0.01)
                            ->required(),

                        Forms\Components\Select::make('metodo_pago_id')
                            ->label('Método de pago')
                            ->options(
                                MetodoPago::where('empresa_id', Filament::getTenant()->id)
                                    ->where('estado', 'activo')
                                    ->pluck('nombre', 'id')
                            )
                            ->required()
                            ->searchable(),

                        Forms\Components\Select::make('categoria')
                            ->label('Categoría')
                            ->options(collect(CategoriaGasto::cases())->mapWithKeys(fn ($c) => [$c->value => $c->getLabel()]))
                            ->default('otro')
                            ->required(),

                        Forms\Components\DatePicker::make('fecha')
                            ->label('Fecha de pago')
                            ->default(now())
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (GastoFijo $record, array $data): void {
                        $empresaId = Filament::getTenant()->id;

                        Gasto::create([
                            'empresa_id'    => $empresaId,
                            'gasto_fijo_id' => $record->id,
                            'user_id'       => auth()->id(),
                            'fecha'         => $data['fecha'],
                            'monto'         => $data['monto'],
                            'metodo_pago_id'=> $data['metodo_pago_id'],
                            'categoria'     => $data['categoria'],
                            'descripcion'   => 'Pago de gasto fijo: ' . $record->nombre,
                            'serie'         => 'GF',
                            'correlativo'   => Gasto::siguienteCorrelativo($empresaId, 'GF'),
                            'estado'        => 'aprobado',
                        ]);

                        $record->avanzarProximaFecha();

                        Notification::make()
                            ->success()
                            ->title('Pago registrado')
                            ->body('S/ ' . number_format($data['monto'], 2) . ' — próximo pago: ' . $record->fresh()->proxima_fecha_pago?->format('d/m/Y'))
                            ->send();
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('proxima_fecha_pago')
            ->emptyStateHeading('Sin gastos fijos registrados')
            ->emptyStateDescription('Agrega tus gastos fijos (alquiler, servicios, planilla…) para calcular tu meta mínima de ventas.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListGastosFijos::route('/'),
            'create' => CreateGastoFijo::route('/create'),
            'edit'   => EditGastoFijo::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('empresa_id', Filament::getTenant()->id);
    }
}

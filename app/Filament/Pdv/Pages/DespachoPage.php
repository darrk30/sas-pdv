<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\EstadoPago;
use App\Enums\EstadoVenta;
use App\Filament\Pdv\Concerns\HasFullWidthPage;
use App\Filament\Pdv\Concerns\HasVentaDetalleModal;
use App\Filament\Pdv\Widgets\DespachoStatsWidget;
use App\Models\Venta;
use BackedEnum;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Actions\Action as TableAction;
use Filament\Actions\ActionGroup as TableActionGroup;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class DespachoPage extends Page implements HasTable
{
    use HasVentaDetalleModal;
    use InteractsWithTable;
    use HasFullWidthPage;

    protected string $view = 'filament.pdv.pages.despacho';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-paper-airplane';
    protected static ?string $navigationLabel = 'Despachos';
    protected static string|UnitEnum|null $navigationGroup = 'Pedidos Web';
    protected static ?int $navigationSort = 4;
    protected static ?string $title = 'Despachos pendientes';

    public function getHeading(): string
    {
        return 'Despachos pendientes';
    }

    public static function canAccess(): bool
    {
        return Filament::getTenant()->tieneModulo('despacho') && (auth()->user()?->can('ordenes.despacho') ?? false);
    }

    public static function getNavigationBadge(): ?string
    {
        $empresaId = Filament::getTenant()?->id;
        if (! $empresaId) return null;

        $count = cache()->remember("badge_despachos_{$empresaId}", 30, fn () =>
            Venta::where('empresa_id', $empresaId)
                ->whereNotNull('estado_despacho')
                ->where('estado', EstadoVenta::Completada)
                ->count()
        );

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    // ── State machine ──────────────────────────────────────────────────────────

    private const ORDEN = [
        'pendiente_envio',
        'en_preparacion',
        'en_agencia',
        'en_camino',
        'entregado',
    ];

    public const META = [
        'pendiente_envio' => ['label' => 'Pendiente',      'color' => 'gray'],
        'en_preparacion'  => ['label' => 'En preparación', 'color' => 'info'],
        'en_agencia'      => ['label' => 'En agencia',     'color' => 'warning'],
        'en_camino'       => ['label' => 'En camino',      'color' => 'primary'],
        'entregado'       => ['label' => 'Entregado',      'color' => 'success'],
    ];

    public static function siguientesEstados(?string $actual): array
    {
        $idx = array_search($actual ?? 'pendiente_envio', self::ORDEN);
        return array_slice(self::ORDEN, ($idx !== false ? $idx : 0) + 1);
    }

    public static function metaEstado(string $estado): array
    {
        return self::META[$estado] ?? ['label' => ucfirst($estado), 'color' => 'gray'];
    }

    // ── Widgets ────────────────────────────────────────────────────────────────

    protected function getHeaderWidgets(): array
    {
        return [DespachoStatsWidget::class];
    }

    // ── Table ──────────────────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Venta::query()
                    ->where('empresa_id', Filament::getTenant()->id)
                    ->where('estado', EstadoVenta::Completada)
                    ->whereNotNull('estado_despacho')
                    ->with(['serie', 'detalles', 'cliente:id,telefono', 'orden:id,numero,venta_id'])
                    ->orderBy('fecha_emision', 'desc')
            )
            ->columns([
                TextColumn::make('comprobante')
                    ->label('Comprobante')
                    ->html()
                    ->state(function (Venta $r) {
                        $comp = e(($r->serie?->serie ?? '??') . '-' . str_pad($r->correlativo, 8, '0', STR_PAD_LEFT));
                        $html = "<span class='font-mono font-bold'>{$comp}</span>";
                        if ($r->orden) {
                            $url = route('filament.pdv.resources.ordenes.view', [
                                'tenant' => Filament::getTenant()->slug,
                                'record' => $r->orden->id,
                            ]);
                            $html .= '<br><a href="' . e($url) . '" style="font-size:.7rem;color:#a78bfa;text-decoration:underline;">' . e($r->orden->codigo) . '</a>';
                        }
                        return $html;
                    }),

                TextColumn::make('fecha_emision')
                    ->label('Fecha')
                    ->state(fn (Venta $r) => Carbon::parse($r->fecha_emision)->format('d/m/Y'))
                    ->description(fn (Venta $r) => Carbon::parse($r->fecha_emision)->format('g:i A'))
                    ->sortable(),

                TextColumn::make('cliente_nombre')
                    ->label('Cliente')
                    ->html()
                    ->state(function (Venta $r) {
                        $nombre   = e($r->cliente_nombre ?: 'Cliente general');
                        $telefono = $r->cliente?->telefono;
                        $html     = "<span class='font-medium'>{$nombre}</span>";
                        if ($telefono) {
                            $tel  = preg_replace('/\D/', '', $telefono);
                            $wa   = 'https://wa.me/' . (strlen($tel) <= 9 ? '51' . $tel : $tel);
                            $html .= '<br><a href="' . $wa . '" target="_blank" rel="noopener"'
                                   . ' style="font-size:.7rem;color:#16a34a;text-decoration:underline;">'
                                   . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"'
                                   . ' class="inline w-3 h-3 mr-0.5"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>'
                                   . e($telefono) . '</a>';
                        }
                        return $html;
                    })
                    ->searchable(query: fn (Builder $q, string $s) =>
                        $q->where('cliente_nombre', 'like', "%{$s}%")
                    ),

                TextColumn::make('cliente_num_doc')
                    ->label('Documento')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('productos')
                    ->label('Productos')
                    ->html()
                    ->state(function (Venta $r): string {
                        $items = $r->detalles->take(3)->map(function ($d) {
                            $cant = rtrim(rtrim(number_format((float) $d->cantidad, 4, '.', ''), '0'), '.');
                            $desc = e($d->descripcion);
                            return "<span class='text-xs text-gray-500 dark:text-slate-400'><strong class='text-gray-700 dark:text-slate-200'>×{$cant}</strong> {$desc}</span>";
                        })->implode('<br>');

                        $resto = $r->detalles->count() - 3;
                        if ($resto > 0) {
                            $items .= "<br><span class='text-xs italic text-gray-400'>+{$resto} más…</span>";
                        }

                        return $items;
                    }),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN')
                    ->sortable()
                    ->alignRight(),

                TextColumn::make('estado_pago')
                    ->label('Pago')
                    ->badge()
                    ->formatStateUsing(fn ($state) => EstadoPago::tryFrom($state)?->getLabel() ?? ucfirst((string) ($state ?? '')))
                    ->color(fn ($state) => EstadoPago::tryFrom($state)?->getColor() ?? 'gray'),

                TextColumn::make('estado_despacho')
                    ->label('Despacho')
                    ->badge()
                    ->formatStateUsing(fn ($state) => self::metaEstado($state ?? 'pendiente_envio')['label'])
                    ->color(fn ($state) => self::metaEstado($state ?? 'pendiente_envio')['color']),
            ])
            ->filters([
                Filter::make('fecha')
                    ->label('Período')
                    ->schema([
                        DatePicker::make('desde')
                            ->label('Desde')
                            ->displayFormat('d/m/Y')
                            ->default(now()->subDays(30)),
                        DatePicker::make('hasta')
                            ->label('Hasta')
                            ->displayFormat('d/m/Y')
                            ->default(now()),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha_emision', '>=', $v))
                        ->when($data['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha_emision', '<=', $v))
                    )
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if (! empty($data['desde'])) {
                            $indicators[] = 'Desde ' . Carbon::parse($data['desde'])->format('d/m/Y');
                        }
                        if (! empty($data['hasta'])) {
                            $indicators[] = 'Hasta ' . Carbon::parse($data['hasta'])->format('d/m/Y');
                        }
                        return $indicators;
                    }),
            ])
            ->recordActions([
                TableAction::make('ver_detalle')
                    ->label('')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->button()
                    ->size('sm')
                    ->tooltip('Ver detalle')
                    ->action(fn (Venta $record) => $this->abrirModalDetalle($record->id)),

                TableAction::make('ticket')
                    ->label('')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->button()
                    ->size('sm')
                    ->tooltip('Imprimir ticket de despacho')
                    ->url(fn (Venta $record) => route('pdv.ticket.despacho', $record->id))
                    ->openUrlInNewTab(),

                TableActionGroup::make([
                    TableAction::make('a_en_preparacion')
                        ->label('En preparación')
                        ->color('info')
                        ->icon('heroicon-o-beaker')
                        ->visible(fn (Venta $r) => in_array('en_preparacion', self::siguientesEstados($r->estado_despacho)))
                        ->requiresConfirmation()
                        ->modalHeading('Cambiar estado de despacho')
                        ->modalDescription(fn (Venta $r) => $this->modalDescripcion($r, 'En preparación'))
                        ->modalSubmitActionLabel('Confirmar')
                        ->action(fn (Venta $r) => $this->cambiarEstadoVenta($r, 'en_preparacion')),

                    TableAction::make('a_en_agencia')
                        ->label('En agencia')
                        ->color('warning')
                        ->icon('heroicon-o-building-office')
                        ->visible(fn (Venta $r) => in_array('en_agencia', self::siguientesEstados($r->estado_despacho)))
                        ->requiresConfirmation()
                        ->modalHeading('Cambiar estado de despacho')
                        ->modalDescription(fn (Venta $r) => $this->modalDescripcion($r, 'En agencia'))
                        ->modalSubmitActionLabel('Confirmar')
                        ->action(fn (Venta $r) => $this->cambiarEstadoVenta($r, 'en_agencia')),

                    TableAction::make('a_en_camino')
                        ->label('En camino')
                        ->color('primary')
                        ->icon('heroicon-o-truck')
                        ->visible(fn (Venta $r) => in_array('en_camino', self::siguientesEstados($r->estado_despacho)))
                        ->requiresConfirmation()
                        ->modalHeading('Cambiar estado de despacho')
                        ->modalDescription(fn (Venta $r) => $this->modalDescripcion($r, 'En camino'))
                        ->modalSubmitActionLabel('Confirmar')
                        ->action(fn (Venta $r) => $this->cambiarEstadoVenta($r, 'en_camino')),

                    TableAction::make('a_entregado')
                        ->label('Entregado')
                        ->color('success')
                        ->icon('heroicon-o-check-circle')
                        ->visible(fn (Venta $r) => in_array('entregado', self::siguientesEstados($r->estado_despacho)))
                        ->requiresConfirmation()
                        ->modalHeading('Marcar como entregado')
                        ->modalDescription(fn (Venta $r) => $this->modalDescripcion($r, 'Entregado'))
                        ->modalSubmitActionLabel('Confirmar entrega')
                        ->action(fn (Venta $r) => $this->cambiarEstadoVenta($r, 'entregado')),
                ])
                ->label('')
                ->icon('heroicon-o-arrows-right-left')
                ->color('warning')
                ->button()
                ->size('sm')
                ->tooltip('Cambiar estado de despacho')
                ->visible(fn (Venta $r) => count(self::siguientesEstados($r->estado_despacho)) > 0),
            ])
            ->toolbarActions([])
            ->emptyStateIcon('heroicon-o-paper-airplane')
            ->emptyStateHeading('Sin despachos pendientes')
            ->emptyStateDescription('Todos los pedidos han sido entregados.')
            ->striped()
            ->paginated([10, 25, 50]);
    }

    // ── Helpers para acciones de estado ───────────────────────────────────────

    private function cambiarEstadoVenta(Venta $record, string $estado): void
    {
        $record->update(['estado_despacho' => $estado === 'entregado' ? null : $estado]);

        Notification::make()
            ->title('Estado actualizado: ' . self::metaEstado($estado)['label'])
            ->success()
            ->send();
    }

    private function modalDescripcion(Venta $record, string $nuevoLabel): string
    {
        $cliente = $record->cliente_nombre ?: 'Cliente general';
        $texto   = "Cliente: {$cliente} → {$nuevoLabel}";

        if ((float) $record->saldo_pendiente > 0) {
            $texto .= ' · ⚠ Saldo pendiente: S/ ' . number_format((float) $record->saldo_pendiente, 2);
        }

        return $texto;
    }
}

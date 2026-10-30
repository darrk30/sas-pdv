<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\CondicionPago;
use App\Enums\EstadoMovimiento;
use App\Enums\EstadoOrden;
use App\Enums\EstadoSesion;
use App\Enums\EstadoSunat;
use App\Enums\EstadoVenta;
use App\Enums\TipoItem;
use App\Enums\TipoMovimiento;
use App\Models\Inventario;
use App\Models\MetodoPago;
use App\Models\Orden;
use App\Models\Promocion;
use App\Models\Serie;
use App\Models\SesionCaja;
use App\Models\Transaccion;
use App\Models\Venta;
use App\Models\VentaPago;
use App\Services\KardexService;
use App\Services\PdfVentaService;
use App\Services\VentaService;
use App\Jobs\GenerarReporteVentasJob;
use App\Models\ReporteDescarga;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use App\Filament\Pdv\Concerns\HasAnulacionSunat;
use App\Filament\Pdv\Concerns\HasConvertirComprobante;
use App\Filament\Pdv\Concerns\HasEmisionNota;
use App\Filament\Pdv\Concerns\HasEnvioVentaDirecto;
use App\Filament\Pdv\Concerns\HasFullWidthPage;
use App\Filament\Pdv\Concerns\HasImpresionTicket;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class ReporteVentasPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use HasAnulacionSunat;
    use HasConvertirComprobante;
    use HasEmisionNota;
    use HasEnvioVentaDirecto;
    use HasFullWidthPage;
    use HasImpresionTicket;

    protected string $view = 'filament.pdv.pages.reporte-ventas';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Reporte de Ventas';
    protected static string|UnitEnum|null $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'Reporte de Ventas';

    public function getHeading(): string
    {
        return static::$title ?? '';
    }

    protected function getHeaderWidgets(): array
    {
        return [\App\Filament\Pdv\Widgets\ReporteVentasStatsWidget::class];
    }

    public function getWidgetData(): array
    {
        return [
            'statsData'  => $this->getResumen(),
            'sparksData' => $this->getSparklines(),
        ];
    }

    public static function canAccess(): bool
    {
        return Filament::getTenant()->tieneModulo('reporte_ventas') && (auth()->user()?->can('caja.reporte_ventas') ?? false);
    }


    // ── Estado de filtros (nullable: Select/DatePicker devuelven null al limpiar)
    public ?string $filtroCliente     = null;
    public ?string $filtroSerie       = null;
    public ?string $filtroCorrelativo = null;
    public ?string $filtroMetodo      = null;
    public ?string $filtroEstado      = null;
    public ?string $filtroOrigen      = null;
    public ?string $filtroFechaDesde  = null;
    public ?string $filtroFechaHasta  = null;

    public function mount(): void
    {
        $this->filtroFechaDesde = today()->startOfDay()->format('Y-m-d H:i:s');
        $this->filtroFechaHasta = today()->endOfDay()->format('Y-m-d H:i:s');
        $this->form->fill([
            'filtroFechaDesde' => $this->filtroFechaDesde,
            'filtroFechaHasta' => $this->filtroFechaHasta,
        ]);
    }

    // ── Form con componentes Filament ─────────────────────────────────────────

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Filtros')
                ->description('Filtra las ventas según los criterios seleccionados.')
                ->columns(1)
                ->collapsible()
                ->collapsed(true)
                ->schema([
                    Grid::make(['default' => 1, 'sm' => 2, 'md' => 3, 'lg' => 4])->schema([

                        TextInput::make('filtroCliente')
                            ->label('Cliente')
                            ->placeholder('Nombre o documento…')
                            ->prefixIcon('heroicon-o-magnifying-glass')
                            ->live(debounce: 300),

                        Select::make('filtroSerie')
                            ->label('Serie')
                            ->placeholder('Todas las series')
                            ->options(fn() => Serie::where('empresa_id', Filament::getTenant()->id)
                                ->orderBy('serie')->pluck('serie', 'serie')->toArray())
                            ->native(false)
                            ->searchable()
                            ->live(),

                        TextInput::make('filtroCorrelativo')
                            ->label('Correlativo')
                            ->placeholder('Ej: 00001')
                            ->live(debounce: 300),

                        Select::make('filtroMetodo')
                            ->label('Método de pago')
                            ->placeholder('Todos los métodos')
                            ->options(fn() => MetodoPago::where('empresa_id', Filament::getTenant()->id)
                                ->orderBy('nombre')->pluck('nombre', 'id')->toArray())
                            ->native(false)
                            ->searchable()
                            ->live(),

                        DateTimePicker::make('filtroFechaDesde')
                            ->label('Desde')
                            ->displayFormat('d/m/Y H:i')
                            ->format('Y-m-d H:i:s')
                            ->seconds(false)
                            ->live(),

                        DateTimePicker::make('filtroFechaHasta')
                            ->label('Hasta')
                            ->displayFormat('d/m/Y H:i')
                            ->format('Y-m-d H:i:s')
                            ->seconds(false)
                            ->live(),

                        Select::make('filtroEstado')
                            ->label('Estado')
                            ->placeholder('Todos los estados')
                            ->options(['completada' => 'Completadas', 'anulada' => 'Anuladas'])
                            ->native(false)
                            ->live(),

                        Select::make('filtroOrigen')
                            ->label('Origen')
                            ->placeholder('Todos los orígenes')
                            ->options(fn() => $this->getOrigenOptions())
                            ->native(false)
                            ->live()
                            ->hidden(fn() => count($this->getOrigenOptions()) < 2),

                    ]),
                ]),
        ]);
    }

    public function hayFiltros(): bool
    {
        return ! empty($this->filtroCliente)
            || ! empty($this->filtroSerie)
            || ! empty($this->filtroCorrelativo)
            || ! empty($this->filtroMetodo)
            || ! empty($this->filtroEstado)
            || ! empty($this->filtroOrigen)
            || ! empty($this->filtroFechaDesde)
            || ! empty($this->filtroFechaHasta);
    }

    public function limpiarFiltros(): void
    {
        $this->filtroCliente     = null;
        $this->filtroSerie       = null;
        $this->filtroCorrelativo = null;
        $this->filtroMetodo      = null;
        $this->filtroEstado      = null;
        $this->filtroOrigen      = null;
        $this->filtroFechaDesde  = null;
        $this->filtroFechaHasta  = null;
        $this->form->fill();
    }

    // ── Opciones de filtros ───────────────────────────────────────────────────

    public function getOrigenOptions(): array
    {
        $empresa = Filament::getTenant();
        $opts    = [];

        if ($empresa->tieneModulo('punto_de_venta')) {
            $opts['pdv']          = 'PDV';
            $opts['pdv_delivery'] = 'PDV Delivery';
        }
        if ($empresa->tieneModulo('restaurante')) {
            $opts['restaurante'] = 'Mesa';
            $opts['llevar']      = 'Llevar';
            $opts['delivery']    = 'Delivery';
        }
        if ($empresa->tieneModulo('ordenes_web')) {
            $opts['web'] = 'Web';
        }

        return $opts;
    }

    // ── Query base con filtros ────────────────────────────────────────────────

    private function aplicarFiltros(Builder $q): Builder
    {
        if (! empty($this->filtroCliente)) {
            $b = $this->filtroCliente;
            $q->where(function ($sub) use ($b) {
                $sub->where('cliente_nombre', 'like', "%{$b}%")
                    ->orWhere('cliente_num_doc', 'like', "%{$b}%");
            });
        }

        if (! empty($this->filtroSerie)) {
            $s = $this->filtroSerie;
            $q->whereHas('serie', fn($sub) => $sub->where('serie', $s));
        }

        if (! empty($this->filtroCorrelativo)) {
            $q->where('correlativo', 'like', "%{$this->filtroCorrelativo}%");
        }

        if (! empty($this->filtroMetodo)) {
            $m = $this->filtroMetodo;
            $q->whereHas('pagos', fn($sub) => $sub->where('metodo_pago_id', $m));
        }

        if (! empty($this->filtroEstado)) {
            $q->where('estado', $this->filtroEstado);
        }

        if (! empty($this->filtroOrigen)) {
            $q->where('tipo', $this->filtroOrigen);
        }

        if (! empty($this->filtroFechaDesde)) {
            $q->where('created_at', '>=', $this->filtroFechaDesde);
        }

        if (! empty($this->filtroFechaHasta)) {
            $q->where('created_at', '<=', $this->filtroFechaHasta);
        }

        return $q;
    }

    // ── Tabla Filament ────────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $q = Venta::where('empresa_id', Filament::getTenant()->id)
                    ->with(['serie', 'pagos.metodoPago', 'cupon'])
                    ->addSelect([
                        'ventas.*',
                        'cupon_codigo_orden' => \App\Models\Orden::select('cupones.codigo')
                            ->join('cupones', 'cupones.id', '=', 'ordenes.cupon_id')
                            ->whereColumn('ordenes.venta_id', 'ventas.id')
                            ->limit(1),
                    ])
                    ->withCount([
                        'detalles as cortesias_count' => fn($q) => $q->where('precio_unitario', 0),
                    ]);

                $this->aplicarFiltros($q);

                return $q;
            })
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('comprobante')
                    ->label('Comprobante')
                    ->state(fn(Venta $r): string => ($r->serie?->serie ?? '---') . '-' . str_pad((string) $r->correlativo, 8, '0', STR_PAD_LEFT))
                    ->description(fn(Venta $r): ?string => (float) $r->saldo_pendiente > 0
                        ? '⚠ Saldo: S/ ' . fmt_precio((float) $r->saldo_pendiente)
                        : null)
                    ->weight('medium')
                    ->searchable(false)
                    ->sortable(false)
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('cliente_nombre')
                    ->label('Cliente')
                    ->description(fn(Venta $r): string => strtoupper($r->cliente_tipo_doc) . ' ' . $r->cliente_num_doc)
                    ->searchable(false)
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('cortesias_count')
                    ->label('Cortesía')
                    ->alignCenter()
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn(int $state): string => $state > 0 ? 'Sí' : '—')
                    ->sortable(false)
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('metodo')
                    ->label('Método')
                    ->state(fn(Venta $r): string => $r->pagos
                        ->filter(fn($p) =>
                        $p->metodoPago?->condicion_pago !== \App\Enums\CondicionPago::Credito
                            || $r->estado_pago === 'pendiente')
                        ->map(fn($p) => $p->metodoPago?->nombre)
                        ->filter()->unique()->implode(', ') ?: '—')
                    ->searchable(false)
                    ->sortable(false)
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('op_gravadas')
                    ->label('Op. Gravada')
                    ->formatStateUsing(fn ($state) => $state !== null ? 'S/ ' . fmt_precio((float) $state) : null)
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('igv')
                    ->label('IGV')
                    ->formatStateUsing(fn ($state) => $state !== null ? 'S/ ' . fmt_precio((float) $state) : null)
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('descuento_total')
                    ->label('Descuento')
                    ->alignEnd()
                    ->html()
                    ->formatStateUsing(function ($state, Venta $record): string {
                        if ((float) $state <= 0) return '<span style="color:var(--fi-color-gray-400)">—</span>';
                        $monto = '<span style="color:#dc2626;font-weight:600">−S/ ' . fmt_precio((float) $state) . '</span>';
                        $codigo = $record->cupon?->codigo ?? $record->cupon_codigo_orden;
                        if (! $codigo) return $monto;
                        $badge = '<br><span style="display:inline-block;font-size:.6rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;background:#dcfce7;color:#15803d;border:1px solid #86efac;border-radius:.25rem;padding:.1rem .35rem">'
                            . e($codigo)
                            . '</span>';
                        return $monto . $badge;
                    })
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => $state !== null ? 'S/ ' . fmt_precio((float) $state) : null)
                    ->alignEnd()
                    ->weight('semibold')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('saldo_pendiente')
                    ->label('Saldo pendiente')
                    ->formatStateUsing(fn ($state) => $state !== null ? 'S/ ' . fmt_precio((float) $state) : null)
                    ->alignEnd()
                    ->color('danger')
                    ->formatStateUsing(fn($state): string => (float) $state > 0 ? 'S/ ' . fmt_precio((float) $state) : '—')
                    ->tooltip('Monto aún no pagado de esta venta')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('estado_sunat')
                    ->label('SUNAT')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('tipo')
                    ->label('Origen')
                    ->badge()
                    ->formatStateUsing(fn(?string $state): string => match ($state) {
                        'pdv'          => 'PDV',
                        'pdv_delivery' => 'PDV Delivery',
                        'restaurante'  => 'Mesa',
                        'delivery'     => 'Delivery',
                        'llevar'       => 'Llevar',
                        'web'          => 'Web',
                        default       => strtoupper($state ?? '—'),
                    })
                    ->color(fn(?string $state): string => match ($state) {
                        'pdv'          => 'info',
                        'pdv_delivery' => 'warning',
                        'restaurante'  => 'success',
                        'delivery'     => 'warning',
                        'llevar'       => 'primary',
                        default       => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('notas')
                    ->label('Notas')
                    ->html()
                    ->formatStateUsing(
                        fn(?string $state): string =>
                        $state
                            ? implode('<br>', array_map(
                                fn(string $s): string => '<span>' . e(trim($s)) . '</span>',
                                preg_split('/\s*\|\s*|\n/', trim($state))
                            ))
                            : '—'
                    )
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('sunat_descripcion')
                    ->label('Desc. SUNAT')
                    ->wrap()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('ver')
                        ->label('Ver detalle')
                        ->icon('heroicon-o-eye')
                        ->modalHeading(fn(Venta $record): string =>
                            ($record->serie?->serie ?? '—') . '-' . str_pad((string) $record->correlativo, 8, '0', STR_PAD_LEFT)
                        )
                        ->modalContent(fn(Venta $record) => view(
                            'filament.pdv.components.venta-detalle-modal',
                            ['venta' => $record->load(['serie', 'detalles.producto:id,codigo_interno', 'pagos.metodoPago'])]
                        ))
                        ->modalFooterActions([]),

                    $this->buildImprimirTicketAction(),

                    Action::make('pdf')
                        ->label('Descargar PDF')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (Venta $record) {
                            $record->loadMissing('empresa');
                            $service = app(PdfVentaService::class);
                            $pdf     = $service->generar($record, $record->empresa);
                            $nombre  = $service->nombreArchivo($record);

                            return response()->streamDownload(
                                fn() => print($pdf->output()),
                                $nombre,
                                ['Content-Type' => 'application/pdf'],
                            );
                        }),

                    Action::make('enviarSunat')
                        ->label('Enviar a SUNAT')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->visible(fn(Venta $record): bool => $record->estado_sunat === \App\Enums\EstadoSunat::PorEnviar)
                        ->requiresConfirmation()
                        ->modalHeading('Enviar comprobante a SUNAT')
                        ->modalDescription(fn(Venta $record): string => 'Se enviará ' . ($record->serie?->serie ?? '') . '-' . str_pad((string) $record->correlativo, 8, '0', STR_PAD_LEFT) . ' directamente a SUNAT.')
                        ->action(fn(Venta $record) => $this->enviarAhora($record)),

                    Action::make('reintentarEnvio')
                        ->label('Reintentar envío')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->visible(fn(Venta $record): bool => $record->estado_sunat === \App\Enums\EstadoSunat::Error)
                        ->requiresConfirmation()
                        ->modalHeading('Reintentar envío a SUNAT')
                        ->modalDescription(fn(Venta $record): string => 'Se reintentará el envío de ' . ($record->serie?->serie ?? '') . '-' . str_pad((string) $record->correlativo, 8, '0', STR_PAD_LEFT) . '.')
                        ->action(fn(Venta $record) => $this->enviarAhora($record)),

                    Action::make('anular')
                        ->label('Anular')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn(Venta $record): bool => ! $record->estaAnulada())
                        ->modalHeading(fn(Venta $record): string =>
                            'Anular ' . ($record->serie?->serie ?? '---') . '-' . str_pad($record->correlativo, 8, '0', STR_PAD_LEFT)
                        )
                        ->modalDescription(fn(Venta $record): string =>
                            'Esta acción no se puede deshacer. Se registrará una devolución de S/ ' . fmt_precio($record->total) . '.'
                        )
                        ->modalIcon('heroicon-o-x-circle')
                        ->modalIconColor('danger')
                        ->form(fn(Venta $record): array => [
                            \Filament\Forms\Components\Toggle::make('revertir_stock')
                                ->label('Revertir inventario')
                                ->helperText('Se devolverá el stock de los productos con control de inventario y se registrará en el kardex.')
                                ->default(true)
                                ->visible(fn() => $this->tieneItemsConStockRecord($record)),
                            \Filament\Forms\Components\TextInput::make('motivo_baja')
                                ->label('Motivo de anulación (SUNAT)')
                                ->default('Error en emisión')
                                ->maxLength(100)
                                ->helperText('Solo aplica para facturas. Las boletas usan el Resumen Diario (RC).')
                                ->visible(fn() => $this->necesitaBajaSunatRecord($record)),
                        ])
                        ->action(fn(Venta $record, array $data) => $this->confirmarAnularRecord(
                            $record,
                            (bool) ($data['revertir_stock'] ?? true),
                            (string) ($data['motivo_baja'] ?? 'Error en emisión'),
                        )),

                    Action::make('enviarBajaSunat')
                        ->label('Enviar baja a SUNAT')
                        ->icon('heroicon-o-archive-box-x-mark')
                        ->color('warning')
                        ->visible(
                            fn(Venta $record): bool =>
                            $record->estaAnulada() && (
                                $this->estadoNecesitaBaja($record) ||
                                ($record->estado_sunat === EstadoSunat::PorDarBaja && is_null($record->resumen_sunat_id))
                            )
                        )
                        ->form([
                            TextInput::make('motivo')
                                ->label('Motivo de anulación')
                                ->default('Error en emisión')
                                ->required()
                                ->maxLength(100)
                                ->helperText('Solo aplica para facturas. Las boletas usan el Resumen Diario (RC).'),
                        ])
                        ->modalHeading('Enviar baja a SUNAT')
                        ->modalDescription(
                            fn(Venta $record): string =>
                            'Se enviará la baja para ' .
                                ($record->serie?->serie ?? '') . '-' . str_pad((string) $record->correlativo, 8, '0', STR_PAD_LEFT) .
                                '. Boletas: nuevo RC con estado="3". Facturas: Comunicación de Baja (RA).'
                        )
                        ->action(fn(Venta $record, array $data) => $this->enviarBajaASunat($record, $data['motivo'])),

                    Action::make('consultarBaja')
                        ->label('Consultar baja SUNAT')
                        ->icon('heroicon-o-arrow-down-circle')
                        ->color('info')
                        ->visible(
                            fn(Venta $record): bool =>
                            $record->estado_sunat === EstadoSunat::PorDarBaja
                                && $this->esFactura($record)
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Consultar estado de baja (RA)')
                        ->modalDescription(
                            fn(Venta $record): string =>
                            'Se consultará el ticket de la Comunicación de Baja para ' .
                                ($record->serie?->serie ?? '') . '-' . str_pad((string) $record->correlativo, 8, '0', STR_PAD_LEFT) . '.'
                        )
                        ->action(fn(Venta $record) => $this->consultarBajaVenta($record)),

                    Action::make('descargarXml')
                        ->label('Descargar XML')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('gray')
                        ->visible(fn(Venta $record): bool => ! empty($record->path_xml))
                        ->url(fn(Venta $record) => route('fe.comprobante.download', [$record->id, 'xml']))
                        ->openUrlInNewTab(),

                    Action::make('descargarCdr')
                        ->label('Descargar CDR')
                        ->icon('heroicon-o-document-check')
                        ->color('success')
                        ->visible(fn(Venta $record): bool => ! empty($record->path_cdr_zip))
                        ->url(fn(Venta $record) => route('fe.comprobante.download', [$record->id, 'cdr']))
                        ->openUrlInNewTab(),

                    $this->buildConvertirAction(),
                    $this->buildNotaAction(),
                ]),
            ])
            ->toolbarActions($this->accionesExportacion())
            ->recordClasses(fn(Venta $r): string => (float) $r->saldo_pendiente > 0 ? 'bg-red-50 dark:bg-red-950/20' : '')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Sin ventas')
            ->emptyStateIcon('heroicon-o-receipt-percent');
    }

    private function accionesExportacion(): array
    {
        return [
            Action::make('descargarExcel')
                ->label('Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->action(fn() => $this->despacharReporte('excel')),

            Action::make('descargarPdf')
                ->label('PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->action(fn() => $this->despacharReporte('pdf')),
        ];
    }

    private const UMBRAL_COLA_EXCEL = 1000;
    private const UMBRAL_COLA_PDF   = 1000;

    private function despacharReporte(string $tipo): mixed
    {
        $umbral = $tipo === 'pdf' ? self::UMBRAL_COLA_PDF : self::UMBRAL_COLA_EXCEL;

        $q = Venta::where('empresa_id', Filament::getTenant()->id);
        $this->aplicarFiltros($q);
        $count = $q->count();

        // Pocos registros → descarga directa e instantánea
        if ($count <= $umbral) {
            $ventas = $q->with(['serie', 'pagos.metodoPago'])
                ->withCount(['detalles', 'notas', 'detalles as cortesias_count' => fn($q) => $q->where('precio_unitario', 0)])
                ->orderBy('created_at', 'desc')
                ->get();

            $service = app(\App\Services\ReporteVentasExportService::class);

            return $tipo === 'pdf'
                ? $service->generarPdf($ventas, [], $this->getColumnasVisibles(), $this->getResumen(), Filament::getTenant())
                : $service->generarExcel($ventas, [], $this->getColumnasVisibles(), $this->getResumen(), Filament::getTenant());
        }

        // Muchos registros → cola en background
        $reporte = ReporteDescarga::create([
            'empresa_id'  => Filament::getTenant()->id,
            'user_id'     => auth()->id(),
            'tipo'        => $tipo,
            'reporte'     => 'ventas',
            'estado'      => 'pendiente',
            'filtros'     => [
                'filtroFechaDesde'  => $this->filtroFechaDesde,
                'filtroFechaHasta'  => $this->filtroFechaHasta,
                'filtroCliente'     => $this->filtroCliente,
                'filtroSerie'       => $this->filtroSerie,
                'filtroCorrelativo' => $this->filtroCorrelativo,
                'filtroMetodo'      => $this->filtroMetodo,
                'filtroEstado'      => $this->filtroEstado,
                'filtroOrigen'      => $this->filtroOrigen,
                'columnasVisibles'  => $this->getColumnasVisibles(),
            ],
        ]);

        GenerarReporteVentasJob::dispatch($reporte->id);

        \Filament\Notifications\Notification::make()
            ->title('Reporte en proceso')
            ->body("Son {$count} registros, se genera en segundo plano. Recibirás una notificación cuando esté listo.")
            ->info()
            ->actions([
                Action::make('ver_descargas')
                    ->label('Ver descargas')
                    ->url(ReporteDescargasPage::getUrl(panel: 'pdv', tenant: Filament::getTenant()))
                    ->button(),
            ])
            ->send();

        return null;
    }

    private function getColumnasVisibles(): array
    {
        $visible = [];

        foreach ($this->tableColumns as $item) {
            if ($item['type'] === 'column' && ($item['isToggled'] ?? false)) {
                $visible[] = $item['name'];
            }
            if ($item['type'] === 'group') {
                foreach ($item['columns'] ?? [] as $col) {
                    if ($col['isToggled'] ?? false) {
                        $visible[] = $col['name'];
                    }
                }
            }
        }

        return $visible;
    }

    // ── Resumen (refleja los filtros activos) ─────────────────────────────────

    public function getResumen(): array
    {
        $base = Venta::where('empresa_id', Filament::getTenant()->id);
        $this->aplicarFiltros($base);

        $completada = EstadoVenta::Completada->value;
        $anulada    = EstadoVenta::Anulada->value;

        $row = (clone $base)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END), 0)                                                                                                                           AS cnt_completadas,
                COALESCE(SUM(CASE WHEN estado = ? THEN monto_pagado ELSE 0 END), 0)                                                                                                               AS total_cobrado,
                COALESCE(SUM(CASE WHEN estado = ? THEN descuento_total ELSE 0 END), 0)                                                                                                            AS descuento_total,
                COALESCE(SUM(CASE WHEN estado = ? AND estado_pago IN ('pendiente','parcial') THEN saldo_pendiente ELSE 0 END), 0)                                                                  AS credito_pendiente,
                COALESCE(SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END), 0)                                                                                                                          AS cnt_anuladas,
                COALESCE(SUM(CASE WHEN estado = ? AND EXISTS(SELECT 1 FROM venta_detalles vd WHERE vd.venta_id = ventas.id AND vd.precio_unitario = 0) THEN 1 ELSE 0 END), 0)                      AS cortesias
            ", [$completada, $completada, $completada, $completada, $anulada, $completada])
            ->first();

        $count            = (int)   $row->cnt_completadas;
        $total            = (float) $row->total_cobrado;
        $descuentoTotal   = (float) $row->descuento_total;
        $creditoPendiente = (float) $row->credito_pendiente;
        $anuladas         = (int)   $row->cnt_anuladas;
        $cortesias        = (int)   $row->cortesias;

        return compact('count', 'total', 'descuentoTotal', 'cortesias', 'anuladas', 'creditoPendiente');
    }

    public function getSparklines(): array
    {
        $rows = DB::table('ventas')
            ->where('empresa_id', Filament::getTenant()->id)
            ->where('estado', EstadoVenta::Completada->value)
            ->whereDate('created_at', '>=', today()->subDays(6)->toDateString())
            ->selectRaw("DATE(created_at) as dia, COUNT(*) as cantidad, COALESCE(SUM(monto_pagado),0) as total")
            ->groupBy('dia')->orderBy('dia')->get()->keyBy('dia');
        $qty = [];
        $tot = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = today()->subDays($i)->toDateString();
            $r = $rows->get($d);
            $qty[] = (int)   ($r?->cantidad ?? 0);
            $tot[] = (float) ($r?->total    ?? 0);
        }
        return ['cantidad' => $qty, 'total' => $tot];
    }

    // ── Modal anular (Filament nativo) ───────────────────────────────────────

    public function tieneItemsConStockRecord(Venta $venta): bool
    {
        $venta->loadMissing(['detalles.producto', 'detalles.variante.producto']);
        $this->ventaAnularId = $venta->id;
        return $this->tieneItemsConStock();
    }

    public function necesitaBajaSunatRecord(Venta $venta): bool
    {
        return Filament::getTenant()->tieneFacturacionElectronica()
            && $this->estadoNecesitaBaja($venta);
    }

    public function confirmarAnularRecord(Venta $venta, bool $revertirStock, string $motivoBaja): void
    {
        $this->ventaAnularId = $venta->id;
        $this->revertirStock = $revertirStock;
        $this->motivoBaja    = $motivoBaja;
        $this->confirmarAnular();
    }

    // ── Modal anular (implementación interna) ────────────────────────────────

    public ?int  $ventaAnularId  = null;
    public bool  $modalAnular    = false;
    public bool  $revertirStock  = true;
    public string $motivoBaja    = 'Error en emisión';

    public function abrirAnular(int $ventaId): void
    {
        $this->ventaAnularId = $ventaId;
        $this->revertirStock = true;
        $this->modalAnular   = true;
    }

    public function cerrarAnular(): void
    {
        $this->ventaAnularId = null;
        $this->modalAnular   = false;
        $this->revertirStock = true;
        $this->motivoBaja    = 'Error en emisión';
    }

    public function getVentaAnular(): ?Venta
    {
        if (! $this->ventaAnularId) return null;

        return Venta::with([
            'serie',
            'detalles.producto',
            'detalles.variante.producto',
            'pagos.metodoPago',
        ])->find($this->ventaAnularId);
    }

    public function necesitaBajaSunat(): bool
    {
        $venta = $this->getVentaAnular();
        if (! $venta) return false;
        $empresa = Filament::getTenant();
        return $empresa->tieneFacturacionElectronica() && $this->estadoNecesitaBaja($venta);
    }

    public function tieneItemsConStock(): bool
    {
        $venta = $this->getVentaAnular();
        if (! $venta) return false;

        foreach ($venta->detalles as $d) {
            if ($d->producto?->control_de_stock)                return true;
            if ($d->variante?->producto?->control_de_stock)     return true;

            // Sin control de stock pero con receta: los insumos sí tienen stock
            if ($d->tipo_item === TipoItem::Producto && $d->producto_id) {
                if (\App\Models\Receta::where('empresa_id', $venta->empresa_id)
                    ->where('producto_id', $d->producto_id)->whereNull('variante_id')
                    ->whereHas('detalles')->exists()) {
                    return true;
                }
            }
            if ($d->tipo_item === TipoItem::Variante && $d->variante_id) {
                if (\App\Models\Receta::where('empresa_id', $venta->empresa_id)
                    ->where('variante_id', $d->variante_id)
                    ->whereHas('detalles')->exists()) {
                    return true;
                }
            }

            // Promo: solo muestra el check si al menos un componente tiene control_de_stock o receta
            if ($d->tipo_item === TipoItem::Promocion && $d->promocion_id) {
                $promo = \App\Models\Promocion::with(['detalles.producto', 'detalles.variante.producto'])->find($d->promocion_id);
                if ($promo) {
                    foreach ($promo->detalles as $pd) {
                        if ($pd->variante_id && $pd->variante?->producto?->control_de_stock) return true;
                        if ($pd->producto_id && $pd->producto?->control_de_stock)            return true;
                        if ($pd->variante_id && \App\Models\Receta::where('empresa_id', $venta->empresa_id)->where('variante_id', $pd->variante_id)->whereHas('detalles')->exists()) return true;
                        if ($pd->producto_id && \App\Models\Receta::where('empresa_id', $venta->empresa_id)->where('producto_id', $pd->producto_id)->whereNull('variante_id')->whereHas('detalles')->exists()) return true;
                    }
                }
            }
        }

        return false;
    }

    public function confirmarAnular(): void
    {
        if (! $this->ventaAnularId) return;

        $venta = Venta::with([
            'serie',
            'detalles.producto',
            'detalles.variante.producto',
            'pagos',
        ])->find($this->ventaAnularId);

        if (! $venta || $venta->empresa_id !== Filament::getTenant()->id) {
            Notification::make()->title('Venta no encontrada')->danger()->send();
            $this->cerrarAnular();
            return;
        }

        if ($venta->estaAnulada()) {
            Notification::make()->title('Esta venta ya está anulada')->warning()->send();
            $this->cerrarAnular();
            return;
        }

        $empresaId   = Filament::getTenant()->id;
        $comprobante = ($venta->serie?->serie ?? '---') . '-' . $venta->correlativo;
        $sesion      = SesionCaja::where('empresa_id', $empresaId)
            ->where('user_id', auth()->id())
            ->where('estado', EstadoSesion::Abierta->value)
            ->latest()->first();
        $revertir    = $this->revertirStock;

        try {
            DB::transaction(function () use ($venta, $empresaId, $comprobante, $sesion, $revertir) {

                $venta->update(['estado' => EstadoVenta::Anulada]);

                Orden::where('venta_id', $venta->id)
                    ->update(['estado' => EstadoOrden::Cancelada]);

                Transaccion::where('transaccionable_type', Venta::class)
                    ->where('transaccionable_id', $venta->id)
                    ->where('tipo', TipoMovimiento::Ingreso->value)
                    ->update(['estado' => EstadoMovimiento::Anulado->value]);

                // Ventas web no tienen caja — se omite el egreso de devolución
                if ($venta->tipo !== 'web' && $sesion) {
                    foreach ($venta->pagos as $pago) {
                        Transaccion::create([
                            'empresa_id'           => $empresaId,
                            'sesion_caja_id'       => $sesion->id,
                            'transaccionable_type' => Venta::class,
                            'transaccionable_id'   => $venta->id,
                            'tipo'                 => TipoMovimiento::Egreso,
                            'concepto'             => "Devolución {$comprobante}",
                            'monto'                => $pago->monto,
                            'metodo_pago_id'       => $pago->metodo_pago_id,
                            'estado'               => EstadoMovimiento::Aprobado,
                            'fecha'                => now(),
                        ]);
                    }
                }

                if ($revertir) {
                    $kardex       = app(KardexService::class);
                    $ventaService = app(VentaService::class);
                    $conceptoRev  = "Reversión {$comprobante}";

                    foreach ($venta->detalles as $detalle) {
                        $cantidad = (float) $detalle->cantidad;

                        if ($detalle->tipo_item === TipoItem::Producto && $detalle->producto_id) {
                            if ($detalle->producto?->control_de_stock) {
                                $inv = Inventario::where('empresa_id', $empresaId)
                                    ->where('producto_id', $detalle->producto_id)
                                    ->whereNull('variante_id')
                                    ->lockForUpdate()->first();

                                if ($inv) {
                                    $antes   = (float) $inv->stock_real;
                                    $despues = $antes + $cantidad;
                                    $inv->update([
                                        'stock_real'    => $despues,
                                        'stock_reserva' => max(0, (float) $inv->stock_reserva + ($despues - $antes)),
                                    ]);
                                    $kardex->registrar([
                                        'empresa_id'        => $empresaId,
                                        'user_id'           => auth()->id(),
                                        'movible'           => $venta,
                                        'producto_id'       => $detalle->producto_id,
                                        'variante_id'       => null,
                                        'producto_nombre'   => $detalle->descripcion,
                                        'tipo'              => 'entrada',
                                        'concepto'          => $conceptoRev,
                                        'cantidad'          => $cantidad,
                                        'unidad'            => 'unidad',
                                        'factor_conversion' => 1,
                                        'cantidad_base'     => $cantidad,
                                        'precio_unitario'   => (float) $detalle->precio_unitario,
                                        'precio_total'      => (float) $detalle->total,
                                        'stock_antes'       => $antes,
                                        'stock_despues'     => $despues,
                                        'fecha'             => now(),
                                    ]);
                                }
                            }
                            // Restaurar insumos de receta (con o sin control_de_stock)
                            $ventaService->restaurarReceta($empresaId, $detalle->producto_id, null, $cantidad, $venta, $conceptoRev);
                        } elseif ($detalle->tipo_item === TipoItem::Variante && $detalle->variante_id) {
                            $varRev = $detalle->variante;
                            $varRev?->loadMissing('producto.unidadMedida');
                            $varianteProd = $varRev?->producto;
                            if ($varianteProd?->control_de_stock) {
                                $factor       = $varRev->factor();
                                $cantidadBase = round($cantidad * $factor, 4);
                                $inv = $varRev->resolverInventario($empresaId, lock: true);

                                if ($inv) {
                                    $antes   = (float) $inv->stock_real;
                                    $despues = $antes + $cantidadBase;
                                    $inv->update([
                                        'stock_real'    => $despues,
                                        'stock_reserva' => max(0, (float) $inv->stock_reserva + $cantidadBase),
                                    ]);
                                    $kCantRp = $varianteProd->comparte_stock ? $cantidadBase : $cantidad;
                                    $kFactRp = $varianteProd->comparte_stock ? 1 : $factor;
                                    $kPrecRp = $varianteProd->comparte_stock && $cantidadBase > 0
                                        ? round((float) $detalle->precio_unitario / $cantidadBase * $cantidad, 6)
                                        : (float) $detalle->precio_unitario;
                                    $kardex->registrar([
                                        'empresa_id'        => $empresaId,
                                        'user_id'           => auth()->id(),
                                        'movible'           => $venta,
                                        'producto_id'       => $varianteProd->id,
                                        'variante_id'       => $detalle->variante_id,
                                        'producto_nombre'   => $detalle->descripcion,
                                        'tipo'              => 'entrada',
                                        'concepto'          => $conceptoRev,
                                        'cantidad'          => $kCantRp,
                                        'unidad'            => $varianteProd->unidadMedida?->nombre ?? 'unidad',
                                        'factor_conversion' => $kFactRp,
                                        'cantidad_base'     => $cantidadBase,
                                        'precio_unitario'   => $kPrecRp,
                                        'precio_total'      => (float) $detalle->total,
                                        'stock_antes'       => $antes,
                                        'stock_despues'     => $despues,
                                        'fecha'             => now(),
                                    ]);
                                }
                            }
                            // Restaurar insumos de receta (con o sin control_de_stock)
                            $ventaService->restaurarReceta($empresaId, null, $detalle->variante_id, $cantidad, $venta, $conceptoRev);
                        } elseif ($detalle->tipo_item === TipoItem::Promocion && $detalle->promocion_id) {
                            Promocion::where('id', $detalle->promocion_id)
                                ->decrement('usos_actuales', (int) $cantidad);

                            $promo = Promocion::with([
                                'detalles.producto',
                                'detalles.variante.producto',
                            ])->find($detalle->promocion_id);

                            if (! $promo) continue;

                            foreach ($promo->detalles as $comp) {
                                $cantComp = $cantidad * (float) $comp->cantidad;

                                if ($comp->variante_id) {
                                    $prodComp = $comp->variante?->producto;
                                    if ($prodComp?->control_de_stock) {
                                        $inv = Inventario::where('empresa_id', $empresaId)
                                            ->where('variante_id', $comp->variante_id)
                                            ->lockForUpdate()->first();
                                        if ($inv) {
                                            $antes   = (float) $inv->stock_real;
                                            $despues = $antes + $cantComp;
                                            $inv->update([
                                                'stock_real'    => $despues,
                                                'stock_reserva' => max(0, (float) $inv->stock_reserva + ($despues - $antes)),
                                            ]);
                                            $kardex->registrar([
                                                'empresa_id'        => $empresaId,
                                                'user_id'           => auth()->id(),
                                                'movible'           => $venta,
                                                'producto_id'       => $comp->variante->producto_id,
                                                'variante_id'       => $comp->variante_id,
                                                'tipo'              => 'entrada',
                                                'concepto'          => $conceptoRev,
                                                'notas'             => "Promo: {$detalle->descripcion}",
                                                'cantidad'          => $cantComp,
                                                'unidad'            => 'unidad',
                                                'factor_conversion' => 1,
                                                'cantidad_base'     => $cantComp,
                                                'stock_antes'       => $antes,
                                                'stock_despues'     => $despues,
                                                'fecha'             => now(),
                                            ]);
                                        }
                                    }
                                    $ventaService->restaurarReceta($empresaId, null, (int) $comp->variante_id, $cantComp, $venta, $conceptoRev);
                                } elseif ($comp->producto_id) {
                                    $prodComp = $comp->producto;
                                    if ($prodComp?->control_de_stock) {
                                        $inv = Inventario::where('empresa_id', $empresaId)
                                            ->where('producto_id', $comp->producto_id)
                                            ->whereNull('variante_id')
                                            ->lockForUpdate()->first();
                                        if ($inv) {
                                            $antes   = (float) $inv->stock_real;
                                            $despues = $antes + $cantComp;
                                            $inv->update([
                                                'stock_real'    => $despues,
                                                'stock_reserva' => max(0, (float) $inv->stock_reserva + ($despues - $antes)),
                                            ]);
                                            $kardex->registrar([
                                                'empresa_id'        => $empresaId,
                                                'user_id'           => auth()->id(),
                                                'movible'           => $venta,
                                                'producto_id'       => $comp->producto_id,
                                                'variante_id'       => null,
                                                'tipo'              => 'entrada',
                                                'concepto'          => $conceptoRev,
                                                'notas'             => "Promo: {$detalle->descripcion}",
                                                'cantidad'          => $cantComp,
                                                'unidad'            => 'unidad',
                                                'factor_conversion' => 1,
                                                'cantidad_base'     => $cantComp,
                                                'stock_antes'       => $antes,
                                                'stock_despues'     => $despues,
                                                'fecha'             => now(),
                                            ]);
                                        }
                                    }
                                    $ventaService->restaurarReceta($empresaId, (int) $comp->producto_id, null, $cantComp, $venta, $conceptoRev);
                                }
                            }
                        }
                    }
                }
            });
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error al anular la venta')
                ->body($e->getMessage())
                ->danger()
                ->send();
            return;
        }

        $motivoBaja = $this->motivoBaja;
        $this->cerrarAnular();

        Notification::make()
            ->title("Venta {$comprobante} anulada")
            ->body($revertir
                ? 'Se registró la devolución y se revirtió el inventario.'
                : 'Se registró la devolución. El inventario no fue modificado.')
            ->warning()
            ->send();

        // Si el comprobante ya fue enviado a SUNAT, enviar Comunicación de Baja (RA)
        if ($this->estadoNecesitaBaja($venta)) {
            $this->enviarBajaASunat($venta, $motivoBaja);
        }
    }
}

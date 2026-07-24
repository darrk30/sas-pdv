<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\EstadoSunat;
use App\Enums\EstadoVenta;
use App\Enums\TipoComprobante;
use App\Filament\Pdv\Concerns\HasFullWidthPage;
use App\Models\Nota;
use App\Models\ResumenSunat;
use App\Models\Venta;
use App\Services\FacturadorService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class ResumenBoletasPage extends Page implements HasTable
{
    use InteractsWithTable;
    use HasFullWidthPage;

    protected string $view = 'filament.pdv.pages.resumen-boletas';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-archive-box-arrow-down';
    protected static ?string $navigationLabel = 'Resumen de Boletas';
    protected static string|UnitEnum|null $navigationGroup = 'Facturación Electrónica';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'Resumenes SUNAT';

    public static function canAccess(): bool
    {
        $empresa = Filament::getTenant();
        if (! $empresa) return false;
        return $empresa->tieneFacturacionElectronica()
            && (auth()->user()?->can('caja.resumen_boletas') ?? false);
    }

    // ── Stat: boletas pendientes de agrupar ───────────────────────────────

    public function getPendientesCount(): int
    {
        $empresaId = Filament::getTenant()->id;

        $nuevas = Venta::where('empresa_id', $empresaId)
            ->where('estado_sunat', EstadoSunat::PorEnviar)
            ->where('estado', '!=', EstadoVenta::Anulada->value)
            ->whereHas('serie', fn ($q) => $q->where('tipo', TipoComprobante::Boleta->value))
            ->whereNull('resumen_sunat_id')
            ->count();

        $bajas = Venta::where('empresa_id', $empresaId)
            ->where('estado_sunat', EstadoSunat::PorDarBaja)
            ->whereHas('serie', fn ($q) => $q->where('tipo', TipoComprobante::Boleta->value))
            ->whereNull('resumen_sunat_id')
            ->count();

        return $nuevas + $bajas;
    }

    // ── Header actions ────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generarResumen')
                ->label('Generar resumen del día')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->modalHeading('Generar Resumen Diario (RC)')
                ->modalDescription('Elige la fecha, revisa las boletas que se incluirán y desmarca las que no quieres enviar.')
                ->modalWidth('2xl')
                ->form([
                    DatePicker::make('fecha')
                        ->label('Fecha de referencia')
                        ->default(now()->format('Y-m-d'))
                        ->maxDate(now()->format('Y-m-d'))
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (?string $state, $set): void {
                            if (! $state) {
                                $set('boleta_ids', []);
                                return;
                            }
                            $set('boleta_ids', array_keys(
                                $this->buildBoletasOptions($state)
                            ));
                        }),

                    CheckboxList::make('boleta_ids')
                        ->label('Boletas a incluir en el RC')
                        ->helperText('Desmarca las boletas que NO quieres enviar en este resumen.')
                        ->options(fn ($get): array => $this->buildBoletasOptions($get('fecha') ?? now()->format('Y-m-d')))
                        ->default(fn (): array => array_keys($this->buildBoletasOptions(now()->format('Y-m-d'))))
                        ->bulkToggleable()
                        ->required()
                        ->minItems(1)
                        ->columns(1),
                ])
                ->action(fn (array $data) => $this->generarYEnviarResumen(
                    Carbon::parse($data['fecha']),
                    $data['boleta_ids'] ?? []
                )),
        ];
    }

    private function buildBoletasOptions(string $fecha): array
    {
        $empresaId = Filament::getTenant()->id;

        $nuevas = Venta::where('empresa_id', $empresaId)
            ->with('serie')
            ->whereHas('serie', fn ($q) => $q->where('tipo', TipoComprobante::Boleta->value))
            ->where('estado_sunat', EstadoSunat::PorEnviar->value)
            ->where('estado', '!=', EstadoVenta::Anulada->value)
            ->whereNull('resumen_sunat_id')
            ->whereDate('fecha_emision', $fecha)
            ->get();

        $bajas = Venta::where('empresa_id', $empresaId)
            ->with('serie')
            ->whereHas('serie', fn ($q) => $q->where('tipo', TipoComprobante::Boleta->value))
            ->where('estado_sunat', EstadoSunat::PorDarBaja->value)
            ->whereNull('resumen_sunat_id')
            ->whereDate('fecha_emision', $fecha)
            ->get();

        $options = [];

        foreach ($nuevas as $v) {
            $num = str_pad((string) $v->correlativo, 8, '0', STR_PAD_LEFT);
            $label = ($v->serie?->serie ?? '?') . '-' . $num
                . ' — S/ ' . number_format($v->total, 2);
            $options[$v->id] = $label;
        }

        foreach ($bajas as $v) {
            $num = str_pad((string) $v->correlativo, 8, '0', STR_PAD_LEFT);
            $label = ($v->serie?->serie ?? '?') . '-' . $num
                . ' — S/ ' . number_format($v->total, 2)
                . ' [ANULADA — dar de baja]';
            $options[$v->id] = $label;
        }

        return $options;
    }

    // ── Lógica: generar y enviar ──────────────────────────────────────────

    protected function generarYEnviarResumen(Carbon $fecha, array $boletaIds = []): void
    {
        $empresa = Filament::getTenant();
        $empresa->loadMissing('facturacion');

        if (! $empresa->tieneFacturacionElectronica()) {
            Notification::make()->title('Empresa sin configuración FE')->danger()->send();
            return;
        }

        // Boletas nuevas (aún no enviadas)
        $qNuevas = Venta::where('empresa_id', $empresa->id)
            ->with('serie')
            ->whereHas('serie', fn ($q) => $q->where('tipo', TipoComprobante::Boleta->value))
            ->where('estado_sunat', EstadoSunat::PorEnviar->value)
            ->where('estado', '!=', EstadoVenta::Anulada->value)
            ->whereNull('resumen_sunat_id')
            ->whereDate('fecha_emision', $fecha);

        if (! empty($boletaIds)) {
            $qNuevas->whereIn('id', $boletaIds);
        }

        $ventasNuevas = $qNuevas->get();

        // Boletas anuladas localmente que necesitan ser reportadas como baja en el RC (estado="3")
        $qBaja = Venta::where('empresa_id', $empresa->id)
            ->with('serie')
            ->whereHas('serie', fn ($q) => $q->where('tipo', TipoComprobante::Boleta->value))
            ->where('estado_sunat', EstadoSunat::PorDarBaja->value)
            ->whereNull('resumen_sunat_id')
            ->whereDate('fecha_emision', $fecha);

        if (! empty($boletaIds)) {
            $qBaja->whereIn('id', $boletaIds);
        }

        $ventasBaja = $qBaja->get();

        $ventas = $ventasNuevas->merge($ventasBaja);

        if ($ventas->isEmpty()) {
            Notification::make()
                ->title('Sin boletas pendientes')
                ->body('No hay boletas por enviar para el ' . $fecha->format('d/m/Y') . '.')
                ->warning()
                ->send();
            return;
        }

        $existing    = ResumenSunat::where('empresa_id', $empresa->id)
            ->whereIn('tipo', ['diario', 'notas_diario'])
            ->whereDate('fecha_referencia', $fecha)
            ->count();
        $nro         = str_pad((string) ($existing + 1), 3, '0', STR_PAD_LEFT);
        $correlativo = 'RC-' . $fecha->format('Ymd') . '-' . $nro;

        $resumen = ResumenSunat::create([
            'empresa_id'       => $empresa->id,
            'tipo'             => 'diario',
            'fecha_referencia' => $fecha->toDateString(),
            'correlativo'      => $correlativo,
            'estado_sunat'     => EstadoSunat::Pendiente->value,
        ]);

        try {
            $service  = app(FacturadorService::class);
            $response = $service->enviarResumen($resumen, $ventas);

            if ($response->ok) {
                $pathXml = null;
                if ($response->xmlBase64) {
                    $pathXml = "empresas/{$empresa->id}/resumenes/{$correlativo}.xml";
                    Storage::disk('local')->put($pathXml, base64_decode($response->xmlBase64));
                }

                $resumen->update([
                    'ticket_sunat' => $response->ticket,
                    'hash'         => $response->hash,
                    'path_xml'     => $pathXml,
                    'estado_sunat' => EstadoSunat::Enviado->value,
                    'fecha_envio'  => now(),
                ]);

                // Boletas nuevas → EnResumen; boletas con baja → PorDarBaja
                $idsBaja = $ventasBaja->pluck('id');
                $idsNuevas = $ventasNuevas->pluck('id');

                if ($idsNuevas->isNotEmpty()) {
                    Venta::whereIn('id', $idsNuevas)->update([
                        'resumen_sunat_id' => $resumen->id,
                        'estado_sunat'     => EstadoSunat::EnResumen->value,
                    ]);
                }
                if ($idsBaja->isNotEmpty()) {
                    Venta::whereIn('id', $idsBaja)->update([
                        'resumen_sunat_id' => $resumen->id,
                    ]);
                }

                $textoConteo = $ventasNuevas->count() . ' boleta(s)';
                if ($ventasBaja->count() > 0) {
                    $textoConteo .= ' + ' . $ventasBaja->count() . ' baja(s)';
                }

                Notification::make()
                    ->title("Resumen {$correlativo} enviado ({$textoConteo})")
                    ->body('Ticket SUNAT: ' . $response->ticket . '. Usa "Consultar estado" para obtener el CDR.')
                    ->success()
                    ->send();
            } else {
                $resumen->update([
                    'estado_sunat' => EstadoSunat::Error->value,
                    'sunat_error'  => $response->mensajeError(),
                    'fecha_envio'  => now(),
                ]);

                Notification::make()
                    ->title("Error al enviar {$correlativo}")
                    ->body($response->mensajeError())
                    ->danger()
                    ->send();
            }
        } catch (\Throwable $e) {
            $resumen->update([
                'estado_sunat' => EstadoSunat::Error->value,
                'sunat_error'  => $e->getMessage(),
            ]);

            Notification::make()
                ->title('Error al enviar resumen')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    // ── Lógica: consultar ticket (RC diario o RA baja) ───────────────────

    protected function consultarEstado(ResumenSunat $resumen): void
    {
        $empresa = Filament::getTenant();
        $empresa->loadMissing('facturacion');
        $config = $empresa->facturacion;

        if (! $config) {
            Notification::make()->title('Sin configuración FE')->danger()->send();
            return;
        }

        try {
            $service = app(FacturadorService::class);

            if ($resumen->tipo->esRA()) {
                $this->consultarEstadoRA($service, $resumen, $empresa, $config);
            } else {
                $this->consultarEstadoRC($service, $resumen, $empresa, $config);
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error al consultar estado')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function consultarEstadoRC(FacturadorService $service, ResumenSunat $resumen, $empresa, $config): void
    {
        $response = $service->consultarEstadoResumen($config, $resumen->ticket_sunat);

        if ($response->ok) {
            $pathCdr = null;
            if ($response->cdrZip) {
                $pathCdr = "empresas/{$empresa->id}/resumenes/{$resumen->correlativo}-CDR.zip";
                Storage::disk('local')->put($pathCdr, base64_decode($response->cdrZip));
            }

            $resumen->update([
                'sunat_success'     => true,
                'sunat_codigo'      => $response->sunatCode,
                'sunat_descripcion' => $response->sunatDescription,
                'sunat_notas'       => $response->sunatNotes ? json_encode($response->sunatNotes) : null,
                'path_cdr_zip'      => $pathCdr,
                'estado_sunat'      => EstadoSunat::Aceptado->value,
                'fecha_respuesta'   => now(),
            ]);

            Venta::where('resumen_sunat_id', $resumen->id)
                ->where('estado_sunat', EstadoSunat::EnResumen->value)
                ->update(['estado_sunat' => EstadoSunat::Aceptado->value]);

            Venta::where('resumen_sunat_id', $resumen->id)
                ->where('estado_sunat', EstadoSunat::PorDarBaja->value)
                ->update(['estado_sunat' => EstadoSunat::DadoDeBaja->value]);

            Nota::where('resumen_sunat_id', $resumen->id)
                ->where('estado_sunat', EstadoSunat::PorDarBaja->value)
                ->update(['estado_sunat' => EstadoSunat::DadoDeBaja->value]);

            Notification::make()
                ->title('RC aceptado por SUNAT')
                ->body("[{$response->sunatCode}] {$response->sunatDescription}")
                ->success()->send();

        } elseif ($response->httpError) {
            // Error de transporte — el resumen sigue "Enviado"; el usuario puede reintentar
            Notification::make()
                ->title('Error de conexión al consultar estado')
                ->body("No se pudo contactar al facturador: {$response->httpError}")
                ->danger()->send();

        } elseif ($response->pending) {
            // SUNAT todavía procesa el ticket (código 98) — no es un rechazo
            Notification::make()
                ->title('SUNAT aún está procesando el RC')
                ->body('El ticket ' . $resumen->ticket_sunat . ' aún no tiene respuesta. Vuelva a consultar en unos minutos.')
                ->warning()->send();

        } else {
            // SUNAT devolvió un rechazo real
            $resumen->update([
                'sunat_success'     => false,
                'sunat_codigo'      => $response->sunatCode ?? $response->errorCode,
                'sunat_descripcion' => $response->sunatDescription ?? $response->errorMessage,
                'estado_sunat'      => EstadoSunat::Rechazado->value,
                'fecha_respuesta'   => now(),
            ]);
            Notification::make()->title('SUNAT rechazó el RC')->body($response->mensajeError())->danger()->send();
        }
    }

    private function consultarEstadoRA(FacturadorService $service, ResumenSunat $resumen, $empresa, $config): void
    {
        $response = $service->consultarEstadoBaja($config, $resumen->ticket_sunat);

        if ($response->ok) {
            $pathCdr = null;
            if ($response->cdrZip) {
                $pathCdr = "empresas/{$empresa->id}/bajas/{$resumen->correlativo}-CDR.zip";
                Storage::disk('local')->put($pathCdr, base64_decode($response->cdrZip));
            }

            $resumen->update([
                'sunat_success'     => true,
                'sunat_codigo'      => $response->sunatCode,
                'sunat_descripcion' => $response->sunatDescription,
                'sunat_notas'       => $response->sunatNotes ? json_encode($response->sunatNotes) : null,
                'path_cdr_zip'      => $pathCdr,
                'estado_sunat'      => EstadoSunat::Aceptado->value,
                'fecha_respuesta'   => now(),
            ]);

            Venta::where('resumen_sunat_id', $resumen->id)
                ->where('estado_sunat', EstadoSunat::PorDarBaja->value)
                ->update(['estado_sunat' => EstadoSunat::DadoDeBaja->value]);

            Nota::where('resumen_sunat_id', $resumen->id)
                ->where('estado_sunat', EstadoSunat::PorDarBaja->value)
                ->update(['estado_sunat' => EstadoSunat::DadoDeBaja->value]);

            Notification::make()
                ->title('Baja (RA) aceptada por SUNAT')
                ->body("[{$response->sunatCode}] {$response->sunatDescription}")
                ->success()->send();

        } elseif ($response->httpError) {
            Notification::make()
                ->title('Error de conexión al consultar estado')
                ->body("No se pudo contactar al facturador: {$response->httpError}")
                ->danger()->send();

        } elseif ($response->pending) {
            Notification::make()
                ->title('SUNAT aún está procesando la baja')
                ->body('El ticket ' . $resumen->ticket_sunat . ' aún no tiene respuesta. Vuelva a consultar en unos minutos.')
                ->warning()->send();

        } else {
            $resumen->update([
                'sunat_success'     => false,
                'sunat_codigo'      => $response->sunatCode ?? $response->errorCode,
                'sunat_descripcion' => $response->sunatDescription ?? $response->errorMessage,
                'estado_sunat'      => EstadoSunat::Rechazado->value,
                'fecha_respuesta'   => now(),
            ]);
            Notification::make()->title('SUNAT rechazó la baja (RA)')->body($response->mensajeError())->danger()->send();
        }
    }

    // ── Lógica: reenviar (mismo ResumenSunat) ────────────────────────────

    protected function reenviarResumen(ResumenSunat $resumen): void
    {
        $empresa = Filament::getTenant();
        $empresa->loadMissing('facturacion');

        // Boletas activas: vinculadas al resumen (incluso rechazado) O pendientes para la misma fecha
        $ventasNuevas = Venta::where('empresa_id', $empresa->id)
            ->with('serie')
            ->whereHas('serie', fn ($q) => $q->where('tipo', TipoComprobante::Boleta->value))
            ->where('estado', '!=', EstadoVenta::Anulada->value)
            ->where(function ($q) use ($resumen) {
                $q->where(function ($sub) use ($resumen) {
                    $sub->where('resumen_sunat_id', $resumen->id)
                        ->whereIn('estado_sunat', [EstadoSunat::EnResumen->value, EstadoSunat::PorEnviar->value]);
                })->orWhere(function ($sub) use ($resumen) {
                    $sub->whereIn('estado_sunat', [EstadoSunat::PorEnviar->value])
                        ->whereNull('resumen_sunat_id')
                        ->whereDate('fecha_emision', $resumen->fecha_referencia);
                });
            })
            ->get();

        // Boletas de baja: vinculadas al resumen O pendientes para la misma fecha
        $ventasBaja = Venta::where('empresa_id', $empresa->id)
            ->with('serie')
            ->whereHas('serie', fn ($q) => $q->where('tipo', TipoComprobante::Boleta->value))
            ->where('estado_sunat', EstadoSunat::PorDarBaja->value)
            ->where(function ($q) use ($resumen) {
                $q->where('resumen_sunat_id', $resumen->id)
                    ->orWhere(function ($sub) use ($resumen) {
                        $sub->whereNull('resumen_sunat_id')
                            ->whereDate('fecha_emision', $resumen->fecha_referencia);
                    });
            })
            ->get();

        $ventas = $ventasNuevas->merge($ventasBaja);

        if ($ventas->isEmpty()) {
            Notification::make()
                ->title('Sin boletas para reenviar')
                ->body('No se encontraron boletas pendientes para el ' . $resumen->fecha_referencia->format('d/m/Y') . '.')
                ->warning()
                ->send();
            return;
        }

        try {
            $service  = app(FacturadorService::class);
            $response = $service->enviarResumen($resumen, $ventas);

            if ($response->ok) {
                $pathXml = null;
                if ($response->xmlBase64) {
                    $pathXml = "empresas/{$empresa->id}/resumenes/{$resumen->correlativo}.xml";
                    Storage::disk('local')->put($pathXml, base64_decode($response->xmlBase64));
                }

                $resumen->update([
                    'ticket_sunat'      => $response->ticket,
                    'hash'              => $response->hash,
                    'path_xml'          => $pathXml,
                    'estado_sunat'      => EstadoSunat::Enviado->value,
                    'sunat_error'       => null,
                    'sunat_success'     => null,
                    'sunat_codigo'      => null,
                    'sunat_descripcion' => null,
                    'fecha_envio'       => now(),
                    'fecha_respuesta'   => null,
                ]);

                if ($ventasNuevas->isNotEmpty()) {
                    Venta::whereIn('id', $ventasNuevas->pluck('id'))->update([
                        'resumen_sunat_id' => $resumen->id,
                        'estado_sunat'     => EstadoSunat::EnResumen->value,
                    ]);
                }
                if ($ventasBaja->isNotEmpty()) {
                    Venta::whereIn('id', $ventasBaja->pluck('id'))->update([
                        'resumen_sunat_id' => $resumen->id,
                    ]);
                }

                Notification::make()
                    ->title("Resumen {$resumen->correlativo} reenviado")
                    ->body('Ticket SUNAT: ' . $response->ticket)
                    ->success()
                    ->send();
            } else {
                $resumen->update([
                    'estado_sunat' => EstadoSunat::Error->value,
                    'sunat_error'  => $response->mensajeError(),
                    'fecha_envio'  => now(),
                ]);

                Notification::make()
                    ->title('Error al reenviar resumen')
                    ->body($response->mensajeError())
                    ->danger()
                    ->send();
            }
        } catch (\Throwable $e) {
            $resumen->update([
                'estado_sunat' => EstadoSunat::Error->value,
                'sunat_error'  => $e->getMessage(),
            ]);

            Notification::make()
                ->title('Error al reenviar resumen')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    // ── Tabla ─────────────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                return ResumenSunat::where('empresa_id', Filament::getTenant()->id)
                    ->whereIn('tipo', ['diario', 'bajas', 'notas_diario', 'notas_bajas'])
                    ->withCount(['ventas', 'notas']);
            })
            ->defaultSort('fecha_referencia', 'desc')
            ->columns([
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge(),

                TextColumn::make('correlativo')
                    ->label('Correlativo')
                    ->weight('medium')
                    ->fontFamily('mono'),

                TextColumn::make('fecha_referencia')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('ventas_count')
                    ->label('Docs.')
                    ->alignCenter()
                    ->badge()
                    ->color('info')
                    ->getStateUsing(fn (ResumenSunat $record): int =>
                        $record->tipo->esParaNotas()
                            ? ($record->notas_count ?? 0)
                            : ($record->ventas_count ?? 0)
                    ),

                TextColumn::make('estado_sunat')
                    ->label('Estado SUNAT')
                    ->badge(),

                TextColumn::make('ticket_sunat')
                    ->label('Ticket')
                    ->color('gray')
                    ->fontFamily('mono')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('sunat_codigo')
                    ->label('Cód.')
                    ->color('gray')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('sunat_descripcion')
                    ->label('Descripción SUNAT')
                    ->wrap()
                    ->color('gray'),

                TextColumn::make('sunat_error')
                    ->label('Error')
                    ->wrap()
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('fecha_envio')
                    ->label('Enviado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('fecha_respuesta')
                    ->label('Respuesta SUNAT')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('consultarEstado')
                        ->label('Consultar estado')
                        ->icon('heroicon-o-magnifying-glass')
                        ->color('info')
                        ->visible(fn (ResumenSunat $record): bool =>
                            $record->estado_sunat === EstadoSunat::Enviado
                            && ! empty($record->ticket_sunat)
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Consultar estado en SUNAT')
                        ->modalDescription(fn (ResumenSunat $record): string =>
                            "Se consultará el ticket {$record->ticket_sunat} para el resumen {$record->correlativo}."
                        )
                        ->action(fn (ResumenSunat $record) => $this->consultarEstado($record)),

                    Action::make('reenviar')
                        ->label(fn (ResumenSunat $record): string =>
                            $record->estado_sunat === EstadoSunat::Rechazado
                                ? 'Reenviar tras rechazo'
                                : 'Reintentar envío'
                        )
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->visible(fn (ResumenSunat $record): bool =>
                            in_array($record->estado_sunat, [EstadoSunat::Error, EstadoSunat::Rechazado])
                            && ! $record->tipo->esParaNotas()
                        )
                        ->requiresConfirmation()
                        ->modalHeading(fn (ResumenSunat $record): string =>
                            $record->estado_sunat === EstadoSunat::Rechazado
                                ? 'Reenviar resumen rechazado'
                                : 'Reintentar envío del resumen'
                        )
                        ->modalDescription(fn (ResumenSunat $record): string =>
                            $record->estado_sunat === EstadoSunat::Rechazado
                                ? "SUNAT rechazó {$record->correlativo}. Se reenviará con las mismas boletas para intentar la aceptación."
                                : "Se reintentará el envío de {$record->correlativo} a SUNAT."
                        )
                        ->action(fn (ResumenSunat $record) => $this->reenviarResumen($record)),

                    Action::make('descargarXml')
                        ->label('Descargar XML')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('gray')
                        ->visible(fn (ResumenSunat $record): bool => ! empty($record->path_xml))
                        ->url(fn (ResumenSunat $record) => route('fe.resumen.download', [$record->id, 'xml']))
                        ->openUrlInNewTab(),

                    Action::make('descargarCdr')
                        ->label('Descargar CDR')
                        ->icon('heroicon-o-document-check')
                        ->color('success')
                        ->visible(fn (ResumenSunat $record): bool => ! empty($record->path_cdr_zip))
                        ->url(fn (ResumenSunat $record) => route('fe.resumen.download', [$record->id, 'cdr']))
                        ->openUrlInNewTab(),
                ]),
            ])
            ->paginated([20, 50])
            ->emptyStateHeading('Sin resúmenes enviados')
            ->emptyStateIcon('heroicon-o-archive-box')
            ->emptyStateDescription('Usa el botón "Generar resumen del día" para agrupar boletas pendientes y enviarlas a SUNAT.');
    }
}

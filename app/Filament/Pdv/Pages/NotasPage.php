<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\EstadoSunat;
use App\Filament\Pdv\Concerns\HasFullWidthPage;
use App\Models\Nota;
use App\Models\ResumenSunat;
use App\Services\FacturadorService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class NotasPage extends Page implements HasTable
{
    use InteractsWithTable;
    use HasFullWidthPage;

    protected string $view               = 'filament.pdv.pages.notas';
    protected static string|BackedEnum|null $navigationIcon  = 'heroicon-o-document-minus';
    protected static ?string $navigationLabel                 = 'Notas (NC / ND)';
    protected static string|UnitEnum|null $navigationGroup   = 'Facturación Electrónica';
    protected static ?int $navigationSort                     = 2;
    protected static ?string $title                          = 'Notas de Crédito y Débito';

    public static function canAccess(): bool
    {
        $empresa = Filament::getTenant();
        if (! $empresa) return false;
        return $empresa->tieneFacturacionElectronica()
            && (auth()->user()?->can('caja.notas') ?? false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Nota::where('empresa_id', Filament::getTenant()->id)
                ->with(['serie', 'venta.serie'])
            )
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge(),

                TextColumn::make('numero')
                    ->label('Número')
                    ->getStateUsing(fn (Nota $record): string =>
                        ($record->serie?->serie ?? '?') . '-' .
                        str_pad((string) $record->correlativo, 8, '0', STR_PAD_LEFT)
                    )
                    ->weight('medium')
                    ->fontFamily('mono'),

                TextColumn::make('afectado')
                    ->label('Comprobante afectado')
                    ->getStateUsing(fn (Nota $record): string =>
                        ($record->venta?->serie?->serie ?? '?') . '-' .
                        str_pad((string) ($record->venta?->correlativo ?? 0), 8, '0', STR_PAD_LEFT)
                    )
                    ->color('gray')
                    ->fontFamily('mono'),

                TextColumn::make('motivo')
                    ->label('Motivo')
                    ->getStateUsing(fn (Nota $record): string =>
                        $record->motivo_codigo . ' – ' . $record->motivo_descripcion
                    )
                    ->wrap()
                    ->color('gray'),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN')
                    ->alignRight(),

                TextColumn::make('estado_sunat')
                    ->label('Estado SUNAT')
                    ->badge(),

                TextColumn::make('sunat_descripcion')
                    ->label('Respuesta SUNAT')
                    ->wrap()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('fecha_emision')
                    ->label('Emitida')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('descargarXml')
                        ->label('Descargar XML')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('gray')
                        ->visible(fn (Nota $record): bool => ! empty($record->path_xml))
                        ->url(fn (Nota $record) => route('fe.nota.download', [$record->id, 'xml']))
                        ->openUrlInNewTab(),

                    Action::make('descargarCdr')
                        ->label('Descargar CDR')
                        ->icon('heroicon-o-document-check')
                        ->color('success')
                        ->visible(fn (Nota $record): bool => ! empty($record->path_cdr_zip))
                        ->url(fn (Nota $record) => route('fe.nota.download', [$record->id, 'cdr']))
                        ->openUrlInNewTab(),

                    Action::make('reintentar')
                        ->label('Reintentar envío a SUNAT')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->visible(fn (Nota $record): bool => in_array(
                            $record->estado_sunat,
                            [EstadoSunat::Error, EstadoSunat::Rechazado, EstadoSunat::PorEnviar]
                        ))
                        ->requiresConfirmation()
                        ->modalHeading('Reintentar envío de la nota')
                        ->action(fn (Nota $record) => $this->reenviarNota($record)),

                    Action::make('anularNota')
                        ->label('Anular ante SUNAT')
                        ->icon('heroicon-o-archive-box-x-mark')
                        ->color('danger')
                        ->visible(fn (Nota $record): bool =>
                            $record->estado_sunat === EstadoSunat::Aceptado
                        )
                        ->form([
                            TextInput::make('motivo')
                                ->label('Motivo de anulación')
                                ->default('Error en emisión')
                                ->required()
                                ->maxLength(100)
                                ->helperText('Para notas de factura (RA). Las notas de boleta usan el RC del mismo día.'),
                        ])
                        ->modalHeading('Anular nota ante SUNAT')
                        ->modalDescription(fn (Nota $record): string =>
                            'Se enviará la baja de ' .
                            ($record->serie?->serie ?? '') . '-' .
                            str_pad((string) $record->correlativo, 8, '0', STR_PAD_LEFT) .
                            ' a SUNAT. Esta acción no se puede deshacer.'
                        )
                        ->action(fn (Nota $record, array $data) =>
                            $this->anularNota($record, $data['motivo'])
                        ),
                ]),
            ])
            ->paginated([25, 50])
            ->emptyStateHeading('Sin notas emitidas')
            ->emptyStateIcon('heroicon-o-document-minus')
            ->emptyStateDescription('Las notas de crédito y débito se emiten desde las acciones de cada venta en Ventas de Turno o Reporte de Ventas.');
    }

    // ── Anulación ante SUNAT ──────────────────────────────────────────────────

    protected function anularNota(Nota $nota, string $motivo): void
    {
        $empresa = Filament::getTenant();
        $empresa->loadMissing('facturacion');

        if (! $empresa->tieneFacturacionElectronica()) {
            Notification::make()->title('Empresa sin configuración FE')->danger()->send();
            return;
        }

        $nota->loadMissing(['serie', 'venta.serie']);

        // Serie empieza con 'F' → nota de factura → RA; 'B' → nota de boleta → RC
        $esDeFactura = strtoupper($nota->serie->serie[0] ?? 'B') === 'F';

        if ($esDeFactura) {
            $this->anularNotaViaRA($nota, $empresa, $motivo);
        } else {
            $this->anularNotaViaRC($nota, $empresa);
        }
    }

    private function anularNotaViaRC(Nota $nota, $empresa): void
    {
        // Marcar PorDarBaja antes de enviar: buildSummaryDetailNota usa este estado
        $nota->update(['estado_sunat' => EstadoSunat::PorDarBaja->value]);
        $nota->estado_sunat = EstadoSunat::PorDarBaja;

        $fechaRef = $nota->fecha_emision->toDateString();

        $existing    = ResumenSunat::where('empresa_id', $empresa->id)
            ->whereIn('tipo', ['diario', 'notas_diario'])
            ->whereDate('fecha_referencia', $fechaRef)
            ->count();
        $nro         = str_pad((string) ($existing + 1), 3, '0', STR_PAD_LEFT);
        $correlativo = 'RC-' . $nota->fecha_emision->format('Ymd') . '-' . $nro;

        $resumen = ResumenSunat::create([
            'empresa_id'       => $empresa->id,
            'tipo'             => 'notas_diario',
            'fecha_referencia' => $fechaRef,
            'correlativo'      => $correlativo,
            'estado_sunat'     => EstadoSunat::Pendiente->value,
        ]);

        try {
            $service  = app(FacturadorService::class);
            $response = $service->enviarResumenNotas($resumen, new EloquentCollection([$nota]));

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

                $nota->update(['resumen_sunat_id' => $resumen->id]);

                Notification::make()
                    ->title("Baja {$correlativo} enviada a SUNAT")
                    ->body('Ticket: ' . $response->ticket . '. Ve a "Resumenes SUNAT" para consultar el CDR.')
                    ->success()->persistent()->send();
            } else {
                $resumen->update([
                    'estado_sunat' => EstadoSunat::Error->value,
                    'sunat_error'  => $response->mensajeError(),
                    'fecha_envio'  => now(),
                ]);

                Notification::make()
                    ->title('Error al enviar baja de nota')
                    ->body($response->mensajeError())
                    ->danger()->send();
            }
        } catch (\Throwable $e) {
            $resumen->update(['estado_sunat' => EstadoSunat::Error->value, 'sunat_error' => $e->getMessage()]);
            Notification::make()->title('Error al anular nota')->body($e->getMessage())->danger()->send();
        }
    }

    private function anularNotaViaRA(Nota $nota, $empresa, string $motivo): void
    {
        $hoy      = now();
        $existing = ResumenSunat::where('empresa_id', $empresa->id)
            ->whereIn('tipo', ['bajas', 'notas_bajas'])
            ->whereDate('fecha_referencia', $hoy)
            ->count();

        $nro         = str_pad((string) ($existing + 1), 3, '0', STR_PAD_LEFT);
        $correlativo = 'RA-NC-' . $hoy->format('Ymd') . '-' . $nro;

        $resumen = ResumenSunat::create([
            'empresa_id'       => $empresa->id,
            'tipo'             => 'notas_bajas',
            'fecha_referencia' => $hoy->toDateString(),
            'correlativo'      => $correlativo,
            'estado_sunat'     => EstadoSunat::Pendiente->value,
        ]);

        try {
            $service  = app(FacturadorService::class);
            $response = $service->enviarBajaNota($resumen, new EloquentCollection([$nota]), $motivo);

            if ($response->ok) {
                $resumen->update([
                    'ticket_sunat' => $response->ticket,
                    'hash'         => $response->hash,
                    'estado_sunat' => EstadoSunat::Enviado->value,
                    'fecha_envio'  => now(),
                ]);

                $nota->update([
                    'estado_sunat'     => EstadoSunat::PorDarBaja->value,
                    'resumen_sunat_id' => $resumen->id,
                ]);

                Notification::make()
                    ->title("Baja {$correlativo} enviada a SUNAT")
                    ->body('Ticket: ' . $response->ticket . '. Ve a "Resumenes SUNAT" para consultar el CDR.')
                    ->success()->persistent()->send();
            } else {
                $resumen->update([
                    'estado_sunat' => EstadoSunat::Error->value,
                    'sunat_error'  => $response->mensajeError(),
                    'fecha_envio'  => now(),
                ]);

                Notification::make()
                    ->title('Error al enviar baja')
                    ->body($response->mensajeError())
                    ->danger()->send();
            }
        } catch (\Throwable $e) {
            $resumen->update(['estado_sunat' => EstadoSunat::Error->value, 'sunat_error' => $e->getMessage()]);
            Notification::make()->title('Error al anular nota')->body($e->getMessage())->danger()->send();
        }
    }

    // ── Reenvío ───────────────────────────────────────────────────────────────

    protected function reenviarNota(Nota $nota): void
    {
        $nota->loadMissing(['venta.detalles.producto', 'venta.serie', 'serie', 'empresa.facturacion']);
        $venta   = $nota->venta;
        $empresa = $nota->empresa;

        try {
            $service  = app(FacturadorService::class);
            $response = $service->enviarNota($nota, $venta);

            $numNota = $nota->serie->serie . '-' . str_pad((string) $nota->correlativo, 8, '0', STR_PAD_LEFT);
            $base    = "empresas/{$empresa->id}/notas/{$numNota}";
            $pathXml = $nota->path_xml;
            $pathCdr = $nota->path_cdr_zip;

            if ($response->xmlBase64) {
                $pathXml = "{$base}.xml";
                Storage::disk('local')->put($pathXml, base64_decode($response->xmlBase64));
            }
            if ($response->cdrZip) {
                $pathCdr = "{$base}-CDR.zip";
                Storage::disk('local')->put($pathCdr, base64_decode($response->cdrZip));
            }

            if ($response->ok) {
                $nota->update([
                    'hash'              => $response->hash,
                    'path_xml'          => $pathXml,
                    'path_cdr_zip'      => $pathCdr,
                    'sunat_success'     => true,
                    'sunat_codigo'      => $response->sunatCode,
                    'sunat_descripcion' => $response->sunatDescription,
                    'estado_sunat'      => EstadoSunat::Aceptado->value,
                ]);

                Notification::make()
                    ->title('Nota aceptada por SUNAT')
                    ->body("[{$response->sunatCode}] {$response->sunatDescription}")
                    ->success()
                    ->send();
            } else {
                $nota->update([
                    'path_xml'          => $pathXml,
                    'sunat_success'     => false,
                    'sunat_codigo'      => $response->sunatCode ?? $response->errorCode,
                    'sunat_descripcion' => $response->mensajeError(),
                    'estado_sunat'      => EstadoSunat::Rechazado->value,
                ]);

                Notification::make()
                    ->title('SUNAT rechazó la nota')
                    ->body($response->mensajeError())
                    ->danger()
                    ->send();
            }
        } catch (\Throwable $e) {
            $nota->update([
                'estado_sunat'      => EstadoSunat::Error->value,
                'sunat_descripcion' => $e->getMessage(),
            ]);

            Notification::make()
                ->title('Error al reenviar nota')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}

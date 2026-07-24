<?php

namespace App\Filament\Pdv\Concerns;

use App\Enums\EstadoSunat;
use App\Enums\TipoComprobante;
use App\Models\Nota;
use App\Models\Serie;
use App\Models\Venta;
use App\Services\FacturadorService;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Storage;

trait HasEmisionNota
{
    private function getMotivosNota(): array
    {
        return [
            '01' => '01 – Anulación de la operación',
            '02' => '02 – Anulación por error en el RUC',
            '03' => '03 – Corrección por error en la descripción',
            '04' => '04 – Descuento global',
            '05' => '05 – Descuento por ítem',
            '06' => '06 – Devolución total',
            '07' => '07 – Devolución por ítem',
            '08' => '08 – Bonificación',
            '09' => '09 – Disminución en el valor',
            '10' => '10 – Otros conceptos',
        ];
    }

    private function getSeriesNota(): array
    {
        return Serie::where('empresa_id', Filament::getTenant()->id)
            ->where('tipo', TipoComprobante::NotaCredito->value)
            ->where('estado', true)
            ->get()
            ->mapWithKeys(fn (Serie $s) => [$s->id => $s->serie])
            ->toArray();
    }

    protected function buildNotaAction(): Action
    {
        $motivos = $this->getMotivosNota();

        return Action::make('emitir_nota_credito')
            ->label('Nota de Crédito')
            ->icon('heroicon-o-arrow-down-circle')
            ->color('success')
            ->visible(fn (Venta $record): bool =>
                $record->estado_sunat === EstadoSunat::Aceptado
                && ! $record->estaAnulada()
            )
            ->form([
                Select::make('serie_id')
                    ->label('Serie')
                    ->options(fn () => $this->getSeriesNota())
                    ->required()
                    ->placeholder('Selecciona serie NC'),

                Select::make('motivo_codigo')
                    ->label('Motivo SUNAT')
                    ->options($motivos)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, $set) use ($motivos): void {
                        if ($state && isset($motivos[$state])) {
                            $partes = explode(' – ', $motivos[$state], 2);
                            $set('motivo_descripcion', $partes[1] ?? '');
                        }
                    }),

                TextInput::make('motivo_descripcion')
                    ->label('Descripción del motivo')
                    ->required()
                    ->maxLength(200),
            ])
            ->modalHeading('Emitir Nota de Crédito')
            ->modalWidth('lg')
            ->modalDescription(fn (Venta $record): string =>
                'Se emitirá una Nota de Crédito referenciando ' .
                ($record->serie?->serie ?? '') . '-' .
                str_pad((string) $record->correlativo, 8, '0', STR_PAD_LEFT) . '.'
            )
            ->action(fn (Venta $record, array $data) =>
                $this->procesarEmisionNota($record, $data)
            );
    }

    protected function procesarEmisionNota(Venta $venta, array $data): void
    {
        $empresa = Filament::getTenant();
        $serie   = Serie::findOrFail($data['serie_id']);

        $ultimoCorrelativo = (int) Nota::where('empresa_id', $empresa->id)
            ->where('serie_id', $serie->id)
            ->max('correlativo');

        $nuevoCorrelativo = $ultimoCorrelativo + 1;
        $numNota = $serie->serie . '-' . str_pad((string) $nuevoCorrelativo, 8, '0', STR_PAD_LEFT);

        $nota = Nota::create([
            'empresa_id'         => $empresa->id,
            'venta_id'           => $venta->id,
            'vendedor_id'        => auth()->id(),
            'tipo'               => 'credito',
            'serie_id'           => $serie->id,
            'correlativo'        => $nuevoCorrelativo,
            'fecha_emision'      => now(),
            'motivo_codigo'      => $data['motivo_codigo'],
            'motivo_descripcion' => $data['motivo_descripcion'],
            'total'              => $venta->total,
            'estado_sunat'       => EstadoSunat::PorEnviar->value,
            'estado'             => 'emitida',
        ]);

        try {
            $service  = app(FacturadorService::class);
            $response = $service->enviarNota($nota, $venta);

            $base    = "empresas/{$empresa->id}/notas/{$numNota}";
            $pathXml = null;
            $pathCdr = null;

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
                    ->title("{$numNota} aceptada por SUNAT")
                    ->body("[{$response->sunatCode}] {$response->sunatDescription}")
                    ->success()
                    ->persistent()
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
                ->title('Error al emitir nota')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}

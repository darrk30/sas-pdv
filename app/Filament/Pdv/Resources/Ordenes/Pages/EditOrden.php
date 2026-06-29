<?php

namespace App\Filament\Pdv\Resources\Ordenes\Pages;

use App\Enums\EstadoOrden;
use App\Enums\EstadoVenta;
use App\Enums\TipoPago;
use App\Filament\Pdv\Resources\Ordenes\OrdenResource;
use App\Models\MetodoPago;
use App\Models\Serie;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\VentaPago;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditOrden extends EditRecord
{
    protected static string $resource = OrdenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirmarPago')
                ->label('Confirmar Pago')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn() => $this->record->estado === EstadoOrden::PendientePago)
                ->modalHeading('Confirmar pago — generar venta')
                ->modalDescription('Registra los pagos recibidos y genera la venta vinculada a esta orden.')
                ->modalWidth('2xl')
                ->fillForm(fn(): array => [
                    'pagos' => [
                        [
                            'metodo_pago_id' => $this->record->metodo_pago_id,
                            'monto'          => (string) $this->record->total,
                            'referencia'     => null,
                        ],
                    ],
                    'listo_para_despachar' => true,
                ])
                ->form([
                    Select::make('serie_id')
                        ->label('Serie del comprobante')
                        ->options(function (): array {
                            return Serie::where('empresa_id', Filament::getTenant()->id)
                                ->where('estado', true)
                                ->get()
                                ->mapWithKeys(fn(Serie $s) => [
                                    $s->id => "{$s->serie} — {$s->tipo->getLabel()}",
                                ])
                                ->all();
                        })
                        ->required()
                        ->native(false),

                    Repeater::make('pagos')
                        ->label('Pagos recibidos')
                        ->minItems(1)
                        ->schema([
                            Select::make('metodo_pago_id')
                                ->label('Método de pago')
                                ->options(function (): array {
                                    return MetodoPago::where('empresa_id', Filament::getTenant()->id)
                                        ->where('estado', 'activo')
                                        ->orderBy('nombre')
                                        ->get()
                                        ->mapWithKeys(fn(MetodoPago $m) => [
                                            $m->id => $m->nombre,
                                        ])
                                        ->all();
                                })
                                ->required()
                                ->native(false),

                            TextInput::make('monto')
                                ->label('Monto')
                                ->numeric()
                                ->prefix('S/')
                                ->required()
                                ->minValue(0.01),

                            TextInput::make('referencia')
                                ->label('N° operación / referencia')
                                ->nullable()
                                ->maxLength(100),
                        ]),

                    Toggle::make('listo_para_despachar')
                        ->label('Por entregar')
                        ->helperText('Aparecerá en la página de Despacho para gestionar el envío.')
                        ->default(true),
                ])
                ->action(function (array $data): void {
                    DB::transaction(function () use ($data): void {
                        $orden   = $this->record->load('detalles');
                        $empresa = Filament::getTenant();

                        $serie       = Serie::lockForUpdate()->findOrFail($data['serie_id']);
                        $nuevoNumero = $serie->numero + 1;
                        $serie->update(['numero' => $nuevoNumero]);
                        $correlativo = str_pad($nuevoNumero, 8, '0', STR_PAD_LEFT);

                        $montoPagado = collect($data['pagos'])->sum('monto');
                        $total       = (float) $orden->total;
                        $saldo       = round($total - $montoPagado, 2);

                        $venta = Venta::create([
                            'empresa_id'       => $empresa->id,
                            'vendedor_id'      => auth()->id(),
                            'sesion_caja_id'   => null,
                            'cliente_id'       => $orden->cliente_id,
                            'tipo'             => 'web',
                            'cliente_nombre'   => $orden->cliente_nombre,
                            'cliente_tipo_doc' => $orden->cliente_tipo_doc,
                            'cliente_num_doc'  => $orden->cliente_num_doc,
                            'serie_id'         => $serie->id,
                            'correlativo'      => $correlativo,
                            'fecha_emision'    => now(),
                            'tipo_pago'        => TipoPago::Contado,
                            'op_gravadas'      => $orden->subtotal,
                            'op_exoneradas'    => 0,
                            'op_inafectas'     => 0,
                            'descuento_total'  => $orden->descuento_total,
                            'igv'              => $orden->igv,
                            'total'            => $orden->total,
                            'costo_total'      => 0,
                            'monto_pagado'     => $montoPagado,
                            'saldo_pendiente'  => max(0, $saldo),
                            'estado_pago'      => $saldo <= 0 ? 'pagado' : 'parcial',
                            'estado'           => EstadoVenta::Completada,
                            'estado_despacho'  => ($data['listo_para_despachar'] ?? false)
                                                    ? 'pendiente_envio'
                                                    : null,
                            'notas'            => $orden->notas,
                        ]);

                        foreach ($orden->detalles as $detalle) {
                            $calc = VentaDetalle::calcular(
                                (float) $detalle->cantidad,
                                (float) $detalle->precio_unitario,
                                0,
                                (float) $detalle->descuento,
                            );

                            VentaDetalle::create([
                                'venta_id'        => $venta->id,
                                'tipo_item'       => $detalle->tipo_item,
                                'producto_id'     => $detalle->producto_id,
                                'variante_id'     => $detalle->variante_id,
                                'descripcion'     => $detalle->descripcion,
                                'cantidad'        => $detalle->cantidad,
                                'precio_unitario' => $detalle->precio_unitario,
                                'valor_unitario'  => $calc['valorUnitario'],
                                'costo_unitario'  => 0,
                                'descuento'       => $detalle->descuento,
                                'subtotal'        => $calc['subtotal'],
                                'valor_total'     => $calc['valorTotal'],
                                'igv'             => $calc['igv'],
                                'total'           => $calc['total'],
                                'costo_total'     => 0,
                            ]);
                        }

                        foreach ($data['pagos'] as $pago) {
                            VentaPago::create([
                                'venta_id'       => $venta->id,
                                'sesion_caja_id' => null,
                                'metodo_pago_id' => $pago['metodo_pago_id'],
                                'tipo'           => 'web',
                                'monto'          => $pago['monto'],
                                'referencia'     => $pago['referencia'] ?? null,
                            ]);
                        }

                        $orden->update([
                            'venta_id' => $venta->id,
                            'estado'   => EstadoOrden::PagoConfirmado,
                        ]);
                    });

                    Notification::make()
                        ->success()
                        ->title('Pago confirmado')
                        ->body('La venta fue generada y vinculada a la orden.')
                        ->send();

                    $this->redirect(static::getResource()::getUrl('edit', ['record' => $this->record]));
                }),

            Action::make('cancelar')
                ->label('Cancelar orden')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('¿Cancelar esta orden?')
                ->modalDescription('La orden será marcada como cancelada. Esta acción no se puede deshacer.')
                ->modalSubmitActionLabel('Sí, cancelar')
                ->visible(fn() => $this->record->estado === EstadoOrden::PendientePago)
                ->action(function (): void {
                    $this->record->update(['estado' => EstadoOrden::Cancelada]);

                    Notification::make()
                        ->warning()
                        ->title('Orden ' . $this->record->codigo . ' cancelada')
                        ->send();

                    $this->redirect(static::getResource()::getUrl('index'));
                }),

            DeleteAction::make()
                ->visible(fn() => $this->record->estado === EstadoOrden::PendientePago),
        ];
    }
}

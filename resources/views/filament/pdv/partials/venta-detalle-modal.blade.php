@if($modalDetalle && $detalleVenta)
<div class="vdm-overlay" wire:click.self="cerrarModalDetalle">
    <div class="vdm-modal" @click.stop>

        {{-- Cabecera ──────────────────────────────────────────────────── --}}
        <div class="vdm-head">
            <div>
                <h2 class="vdm-titulo">{{ $detalleVenta['comprobante'] }}</h2>
                <span class="vdm-sub">{{ $detalleVenta['fecha'] }} · {{ $detalleVenta['vendedor'] }}</span>
            </div>
            <button wire:click="cerrarModalDetalle" class="vdm-cerrar" type="button">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Cuerpo ────────────────────────────────────────────────────── --}}
        <div class="vdm-body">

            {{-- Info cliente + tipo pago ─────────────────────────────── --}}
            <div class="vdm-meta">
                <div class="vdm-meta__item">
                    <span class="vdm-meta__label">Cliente</span>
                    <span class="vdm-meta__val">
                        {{ $detalleVenta['cliente'] }}
                        @if($detalleVenta['cliente_doc'])
                            <small class="vdm-meta__doc">{{ $detalleVenta['cliente_doc'] }}</small>
                        @endif
                    </span>
                </div>
                <div class="vdm-meta__item">
                    <span class="vdm-meta__label">Tipo de pago</span>
                    <span>
                        @if($detalleVenta['tipo_pago'] === 'credito')
                            <span class="vdm-badge vdm-badge--credito">Crédito
                                @if($detalleVenta['fecha_vencimiento'])
                                    · vence {{ $detalleVenta['fecha_vencimiento'] }}
                                @endif
                            </span>
                        @else
                            <span class="vdm-badge vdm-badge--contado">Contado</span>
                        @endif
                    </span>
                </div>
            </div>

            {{-- Tabla de ítems ───────────────────────────────────────── --}}
            <div class="vdm-items-wrap">
                <table class="vdm-items">
                    <thead>
                        <tr>
                            <th>Descripción</th>
                            <th class="text-right">Cant.</th>
                            <th class="text-right">P. Unit.</th>
                            @if(collect($detalleItems)->sum('descuento') > 0)
                            <th class="text-right">Desc.</th>
                            @endif
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($detalleItems as $item)
                        <tr>
                            <td>{{ $item['descripcion'] }}</td>
                            <td class="text-right vdm-num">
                                {{ $item['cantidad'] == intval($item['cantidad'])
                                    ? number_format($item['cantidad'], 0)
                                    : number_format($item['cantidad'], 3) }}
                            </td>
                            <td class="text-right vdm-num">S/ {{ number_format($item['precio_unitario'], 2) }}</td>
                            @if(collect($detalleItems)->sum('descuento') > 0)
                            <td class="text-right vdm-num vdm-num--desc">
                                {{ $item['descuento'] > 0 ? '−S/ ' . number_format($item['descuento'], 2) : '—' }}
                            </td>
                            @endif
                            <td class="text-right vdm-num vdm-num--total">S/ {{ number_format($item['total'], 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Totales ──────────────────────────────────────────────── --}}
            <div class="vdm-totales">
                @if($detalleVenta['igv'] > 0)
                <div class="vdm-totales__fila">
                    <span>Subtotal (sin IGV)</span>
                    <span>S/ {{ number_format($detalleVenta['total'] - $detalleVenta['igv'], 2) }}</span>
                </div>
                <div class="vdm-totales__fila">
                    <span>IGV (18%)</span>
                    <span>S/ {{ number_format($detalleVenta['igv'], 2) }}</span>
                </div>
                @endif
                <div class="vdm-totales__fila vdm-totales__fila--total">
                    <span>Total</span>
                    <span>S/ {{ number_format($detalleVenta['total'], 2) }}</span>
                </div>

                @if(count($detallePagos) > 0)
                <div class="vdm-totales__sep"></div>
                @foreach($detallePagos as $pago)
                <div class="vdm-totales__fila vdm-totales__fila--pago">
                    <span>{{ $pago['metodo'] }}</span>
                    <span>S/ {{ number_format($pago['monto'], 2) }}</span>
                </div>
                @endforeach
                @endif

                @if($detalleVenta['saldo_pendiente'] > 0)
                <div class="vdm-totales__sep"></div>
                <div class="vdm-totales__fila vdm-totales__fila--pendiente">
                    <span>Saldo pendiente</span>
                    <span>S/ {{ number_format($detalleVenta['saldo_pendiente'], 2) }}</span>
                </div>
                @endif
            </div>

        </div>

        {{-- Pie ───────────────────────────────────────────────────────── --}}
        <div class="vdm-foot">
            <button wire:click="cerrarModalDetalle" class="vdm-btn-cerrar" type="button">Cerrar</button>
        </div>

    </div>
</div>
@endif

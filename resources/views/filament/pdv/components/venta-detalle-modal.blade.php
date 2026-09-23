<div class="vd-root">

    {{-- Estado + fecha --}}
    <div class="vd-meta">
        @if($venta->estado === \App\Enums\EstadoVenta::Anulada)
            <span class="vd-badge vd-badge--anulada">Anulada</span>
        @else
            <span class="vd-badge vd-badge--ok">Completada</span>
        @endif
        @if($venta->estado_despacho === \App\Enums\EstadoVenta::PendienteEnvio)
            <span class="vd-badge vd-badge--despacho">Pendiente de envío</span>
        @endif
        <span class="vd-fecha">{{ $venta->created_at->format('d/m/Y H:i') }}</span>
    </div>

    {{-- Cliente --}}
    <div class="vd-cliente">
        <strong>{{ $venta->cliente_nombre }}</strong>
        <span>{{ strtoupper($venta->cliente_tipo_doc ?? '') }} {{ $venta->cliente_num_doc }}</span>
    </div>

    @if($venta->despacho_direccion)
    <div class="vd-direccion">📍 {{ $venta->despacho_direccion }}</div>
    @endif

    {{-- Ítems --}}
    <p class="vd-section-label">Ítems</p>
    <div class="vd-table-wrap">
        <table class="vd-table">
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th class="vd-ta-r">Cant.</th>
                    <th class="vd-ta-r">P. Unit.</th>
                    <th class="vd-ta-r">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($venta->detalles as $d)
                <tr class="{{ $d->precio_unitario == 0 ? 'vd-cortesia' : '' }}">
                    <td>
                        {{ $d->descripcion }}
                        @if($d->precio_unitario == 0)
                            <span class="vd-cortesia-tag">cortesía</span>
                        @endif
                    </td>
                    <td class="vd-ta-r">{{ number_format($d->cantidad, 0) }}</td>
                    <td class="vd-ta-r">S/ {{ number_format($d->precio_unitario, 2) }}</td>
                    <td class="vd-ta-r vd-bold">S/ {{ number_format($d->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Totales --}}
    <div class="vd-totales">
        @if($venta->descuento_total > 0)
        <div class="vd-total-fila vd-total-fila--desc">
            <span>Descuento</span>
            <span>- S/ {{ number_format($venta->descuento_total, 2) }}</span>
        </div>
        @endif
        <div class="vd-total-fila">
            <span>Op. Gravada</span>
            <span>S/ {{ number_format($venta->op_gravadas, 2) }}</span>
        </div>
        <div class="vd-total-fila">
            <span>IGV (18%)</span>
            <span>S/ {{ number_format($venta->igv, 2) }}</span>
        </div>
        <div class="vd-total-fila vd-total-fila--grande">
            <span>Total</span>
            <span>S/ {{ number_format($venta->total, 2) }}</span>
        </div>
    </div>

    {{-- Pagos --}}
    <p class="vd-section-label">Pagos</p>
    <div class="vd-pagos">
        @foreach($venta->pagos as $pago)
        <div class="vd-pago-item">
            <div class="vd-pago-info">
                <span class="vd-pago-metodo">{{ $pago->metodoPago?->nombre ?? '—' }}</span>
                @if($pago->referencia)
                    <span class="vd-pago-ref">{{ $pago->referencia }}</span>
                @endif
            </div>
            <span class="vd-pago-monto">S/ {{ number_format($pago->monto, 2) }}</span>
        </div>
        @endforeach
        @if($venta->saldo_pendiente > 0)
        <div class="vd-pago-item vd-pago-item--saldo">
            <span>Saldo pendiente</span>
            <span>S/ {{ number_format($venta->saldo_pendiente, 2) }}</span>
        </div>
        @endif
    </div>

</div>

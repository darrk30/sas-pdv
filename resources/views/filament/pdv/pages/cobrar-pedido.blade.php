<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/cobrar-pedido.css') }}?v={{ filemtime(public_path('css/cobrar-pedido.css')) }}">

@php
    $orden       = $record->load(['detalles', 'mesa.piso', 'vendedor']);
    $series      = $this->getSeries();
    $metodos     = $this->getMetodosPago();
    $totalBase   = (float) $orden->total;
    $descuento   = $this->getDescuento();
    $totalDesc   = $this->getTotalConDescuento();
    $totalPagado = collect($pagosAgregados)->sum('monto');
    $vuelto      = max(0, $totalPagado - $totalDesc);
    $empresa     = \Filament\Facades\Filament::getTenant();
    $tieneFE     = $empresa->tieneFacturacionElectronica();
    $igvPct      = (float) ($empresa->igv_porcentaje ?? 18);
    $esFE        = in_array($tipoComprobante, ['boleta', 'factura']);
    $baseImpon   = $esFE ? round($totalDesc / (1 + $igvPct / 100), 2) : 0;
    $igvMonto    = $esFE ? round($totalDesc - $baseImpon, 2) : 0;

    $metodoActual  = $metodos->firstWhere('id', $metodoPagoId);
    $esCredito     = $metodoActual?->condicion_pago === \App\Enums\CondicionPago::Credito;
    $puedeConfirmar = $totalDesc <= 0 || ! empty($pagosAgregados);

    $iconoPorNombre = function (string $nombre): string {
        $n = mb_strtolower($nombre);
        if (str_contains($n, 'efectivo') || str_contains($n, 'cash'))
            return 'heroicon-o-banknotes';
        if (str_contains($n, 'tarjeta') || str_contains($n, 'visa') || str_contains($n, 'master') || str_contains($n, 'débito'))
            return 'heroicon-o-credit-card';
        if (str_contains($n, 'yape') || str_contains($n, 'plin') || str_contains($n, 'lukita') || str_contains($n, 'qr'))
            return 'heroicon-o-device-phone-mobile';
        if (str_contains($n, 'transf') || str_contains($n, 'banco') || str_contains($n, 'deposit'))
            return 'heroicon-o-building-library';
        return 'heroicon-o-currency-dollar';
    };

    $comprobantes = [
        ['tipo' => 'boleta',  'label' => 'Boleta',  'desc' => 'Venta al consumidor final', 'icon' => 'heroicon-o-document-text',  'soloFE' => true],
        ['tipo' => 'factura', 'label' => 'Factura', 'desc' => 'Con RUC',                   'icon' => 'heroicon-o-document-check', 'soloFE' => true],
        ['tipo' => 'ticket',  'label' => 'Ticket',  'desc' => 'Uso interno',               'icon' => 'heroicon-o-ticket',         'soloFE' => false],
    ];
@endphp

<livewire:pdv.nuevo-cliente-modal wire:key="nuevo-cliente-modal" />
<livewire:pdv.venta-completada-modal wire:key="venta-completada-modal" />

<div class="cp-layout">

    {{-- ═══════════════════ COLUMNA IZQUIERDA ═══════════════════ --}}
    <div class="cp-left">

        {{-- 1. Tipo de comprobante --}}
        <div class="cp-section">
            <div class="cp-section__head">
                <x-filament::icon icon="heroicon-o-document-duplicate" class="cp-section__icon" />
                <span class="cp-section__title">Tipo de comprobante</span>
                <span class="cp-section__hint">Selecciona el tipo de comprobante para la venta</span>
            </div>
            <div class="cp-comp-grid">
                @foreach($comprobantes as $c)
                    @if($c['soloFE'] && ! $tieneFE) @continue @endif
                    @php $serie = $series->firstWhere('tipo.value', $c['tipo']); @endphp
                    @if(! $serie) @continue @endif
                    @php $activo = $tipoComprobante === $c['tipo']; @endphp
                    <button
                        class="cp-comp-card {{ $activo ? 'cp-comp-card--activo' : '' }}"
                        wire:click="seleccionarComprobante('{{ $c['tipo'] }}')"
                    >
                        <x-filament::icon icon="{{ $c['icon'] }}" class="cp-comp-card__icon {{ $activo ? 'cp-comp-card__icon--activo' : '' }}" />
                        <div class="cp-comp-card__info">
                            <span class="cp-comp-card__label">{{ $c['label'] }}</span>
                            <span class="cp-comp-card__desc">
                                @if($activo && $c['tipo'] === 'factura' && $clienteTipoDoc !== 'ruc')
                                    <span style="color:#dc2626;">Requiere RUC</span>
                                @else
                                    {{ $c['desc'] }}
                                @endif
                            </span>
                        </div>
                        <div class="cp-comp-card__radio {{ $activo ? 'cp-comp-card__radio--activo' : '' }}">
                            @if($activo)<div class="cp-comp-card__radio-dot"></div>@endif
                        </div>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- 2. Cliente --}}
        <div class="cp-section">
            <div class="cp-section__head">
                <x-filament::icon icon="heroicon-o-user" class="cp-section__icon" />
                <span class="cp-section__title">Cliente</span>
                <button class="cp-link" wire:click="abrirModalNuevoCliente">
                    <x-filament::icon icon="heroicon-m-plus" style="width:.8rem;height:.8rem;" />
                    Nuevo cliente
                </button>
            </div>

            @if($clienteId)
            <div class="cp-cliente-card">
                <div class="cp-cliente-card__avatar">
                    <x-filament::icon icon="heroicon-o-user-circle" style="width:1.6rem;height:1.6rem;color:#6b7280;" />
                </div>
                <div class="cp-cliente-card__info">
                    <span class="cp-cliente-card__nombre">{{ $clienteNombre }}</span>
                    @if($clienteTipoDoc && $clienteId)
                        @php $cl = \App\Models\Cliente::find($clienteId); @endphp
                        <span class="cp-cliente-card__doc">{{ strtoupper($clienteTipoDoc) }}: {{ $cl?->numero_documento ?? '—' }}</span>
                    @endif
                </div>
                <button class="cp-cliente-card__cambiar" wire:click="limpiarCliente">
                    <x-filament::icon icon="heroicon-m-arrows-right-left" style="width:.8rem;height:.8rem;" />
                    Cambiar
                </button>
            </div>
            @else
            <div class="cp-search-wrap">
                <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                    <x-filament::input
                        type="text"
                        placeholder="Buscar por nombre, DNI o RUC…"
                        wire:model.live.debounce.300ms="clienteBusqueda"
                        autocomplete="off"
                    />
                </x-filament::input.wrapper>
            </div>
            @if($mostrarSugerencias)
            <div class="cp-sug-list">
                @forelse($this->getClientesSugeridos() as $c)
                <div class="cp-sug-item" wire:click="seleccionarCliente({{ $c->id }})">
                    <x-filament::icon icon="heroicon-m-user" style="width:.8rem;height:.8rem;color:#9ca3af;flex-shrink:0;" />
                    <span>{{ $c->nombre_completo }}</span>
                    @if($c->numero_documento)
                    <span class="cp-sug-doc">{{ strtoupper($c->tipo_documento->value) }} {{ $c->numero_documento }}</span>
                    @endif
                </div>
                @empty
                <p class="cp-sug-empty">Sin resultados</p>
                @endforelse
            </div>
            @endif
            @endif

            @if($esCredito && ! $clienteId)
            <div class="cp-aviso">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" style="width:.85rem;height:.85rem;" />
                <span>Crédito requiere un cliente con DNI o RUC</span>
            </div>
            @endif
        </div>

        {{-- 3. Resumen de productos --}}
        <div class="cp-section">
            <div class="cp-section__head">
                <x-filament::icon icon="heroicon-o-shopping-bag" class="cp-section__icon" />
                <span class="cp-section__title">Resumen de productos</span>
            </div>
            <table class="cp-table">
                <thead>
                    <tr>
                        <th class="cp-th cp-th--num">#</th>
                        <th class="cp-th">Producto</th>
                        <th class="cp-th cp-th--center">Cant.</th>
                        <th class="cp-th cp-th--right">Precio (S/)</th>
                        <th class="cp-th cp-th--right">Subtotal (S/)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orden->detalles as $idx => $det)
                    <tr class="cp-tr">
                        <td class="cp-td cp-td--num">{{ $idx + 1 }}</td>
                        <td class="cp-td">
                            <span class="cp-prod-nombre">{{ $det->descripcion }}</span>
                        </td>
                        <td class="cp-td cp-td--center">
                            <span class="cp-cant">{{ (float)$det->cantidad == (int)$det->cantidad ? (int)$det->cantidad : $det->cantidad }}</span>
                        </td>
                        <td class="cp-td cp-td--right">S/ {{ number_format($det->precio_unitario, 2) }}</td>
                        <td class="cp-td cp-td--right cp-td--bold">S/ {{ number_format($det->total, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- 4. Métodos de pago --}}
        <div class="cp-section">
            <div class="cp-section__head">
                <x-filament::icon icon="heroicon-o-credit-card" class="cp-section__icon" />
                <span class="cp-section__title">Métodos de pago</span>
                <span class="cp-section__hint">Selecciona un método de pago y completa los datos</span>
            </div>

            <div class="cp-metodos-grid">
                @foreach($metodos as $m)
                    @if($tipoComprobante !== 'ticket' && $m->condicion_pago === \App\Enums\CondicionPago::Credito)
                        @continue
                    @endif
                    @php $mActivo = $metodoPagoId === $m->id; @endphp
                    <button
                        class="cp-metodo-card {{ $mActivo ? 'cp-metodo-card--activo' : '' }}"
                        wire:click="$set('metodoPagoId', {{ $m->id }})"
                    >
                        @if($m->imagen)
                            <img src="{{ asset('storage/' . $m->imagen) }}" alt="{{ $m->nombre }}" class="cp-metodo-card__img" />
                        @else
                            <x-filament::icon icon="{{ $iconoPorNombre($m->nombre) }}" class="cp-metodo-card__icon {{ $mActivo ? 'cp-metodo-card__icon--activo' : '' }}" />
                        @endif
                        <span class="cp-metodo-card__label">{{ $m->nombre }}</span>
                        <div class="cp-metodo-card__radio {{ $mActivo ? 'cp-metodo-card__radio--activo' : '' }}">
                            @if($mActivo)<div class="cp-metodo-card__radio-dot"></div>@endif
                        </div>
                    </button>
                @endforeach
            </div>

            @if($tipoComprobante === 'ticket' && $esCredito)
            <div class="cp-field-group">
                <div class="cp-field-label">Fecha de vencimiento</div>
                <x-filament::input.wrapper prefix-icon="heroicon-o-calendar-days">
                    <x-filament::input type="date" wire:model.live="fechaVencimientoCredito" :min="now()->addDay()->toDateString()" />
                </x-filament::input.wrapper>
                @if($fechaVencimientoCredito)
                <p class="cp-hint-text">Vence {{ \Carbon\Carbon::parse($fechaVencimientoCredito)->format('d/m/Y') }} ({{ \Carbon\Carbon::parse($fechaVencimientoCredito)->diffForHumans() }})</p>
                @endif
            </div>
            @endif

            @if($metodoActual?->requiere_referencia)
            <div class="cp-field-group">
                <div class="cp-field-label">N° operación / referencia</div>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model="pagoReferencia" placeholder="Código, N° operación…" />
                </x-filament::input.wrapper>
            </div>
            @endif

            {{-- Monto + Vuelto preview --}}
            <div class="cp-pago-fila">
                <div class="cp-field-group" style="flex:1;min-width:0;">
                    <div class="cp-field-label">Monto recibido (S/)</div>
                    <x-filament::input.wrapper prefix="S/">
                        <x-filament::input
                            type="number"
                            wire:model.live.debounce.300ms="montoPagoInput"
                            min="0" step="0.01"
                            placeholder="{{ number_format($totalDesc, 2) }}"
                        />
                    </x-filament::input.wrapper>
                </div>
                @if($vuelto > 0.005)
                <div class="cp-vuelto-preview">
                    <div class="cp-vuelto-preview__label">Vuelto (S/)</div>
                    <div class="cp-vuelto-preview__valor">S/ {{ number_format($vuelto, 2) }}</div>
                </div>
                @endif
            </div>

            <x-filament::button
                wire:click="agregarPago"
                icon="heroicon-m-plus"
                color="primary"
                size="sm"
                style="margin-top:.4rem;"
            >
                Agregar pago
            </x-filament::button>

            @if(! empty($pagosAgregados))
            <div class="cp-pagos-lista">
                @foreach($pagosAgregados as $i => $pago)
                <div class="cp-pago-item">
                    <span class="cp-pago-item__nombre">
                        {{ $pago['nombre'] }}
                        @if(($pago['condicion_pago'] ?? '') === 'credito')
                        <x-filament::badge color="warning" size="xs">CRÉDITO</x-filament::badge>
                        @endif
                    </span>
                    <span class="cp-pago-item__monto">S/ {{ number_format($pago['monto'], 2) }}</span>
                    <button class="cp-pago-del" wire:click="eliminarPago({{ $i }})" title="Eliminar">
                        <x-filament::icon icon="heroicon-m-x-mark" style="width:.8rem;height:.8rem;" />
                    </button>
                </div>
                @endforeach
            </div>
            @endif
        </div>

    </div>{{-- /cp-left --}}

    {{-- ═══════════════════ COLUMNA DERECHA: RESUMEN ═══════════════════ --}}
    <div class="cp-right">
        <div class="cp-resumen-card">

            <div class="cp-resumen-head">
                <div class="cp-resumen-head__left">
                    <x-filament::icon icon="heroicon-o-calculator" style="width:1rem;height:1rem;color:#6b7280;" />
                    <span class="cp-resumen-head__title">Resumen de pago</span>
                </div>
                @if($orden->numero)
                <span class="cp-resumen-head__num">Pedido #{{ $orden->numero }}</span>
                @endif
            </div>

            <div class="cp-resumen-filas">
                @if($esFE)
                <div class="cp-rf">
                    <span>Op. gravada</span>
                    <span>S/ {{ number_format($baseImpon, 2) }}</span>
                </div>
                <div class="cp-rf">
                    <span>IGV ({{ (int)$igvPct }}%)</span>
                    <span>S/ {{ number_format($igvMonto, 2) }}</span>
                </div>
                @else
                <div class="cp-rf">
                    <span>Subtotal</span>
                    <span>S/ {{ number_format($totalBase, 2) }}</span>
                </div>
                @endif

                <div class="cp-rf {{ $descuento > 0 ? 'cp-rf--desc-activo' : '' }}">
                    <div style="display:flex;align-items:center;gap:.4rem;">
                        <span>Descuento</span>
                        <x-filament::input.wrapper style="width:72px;">
                            <x-filament::input
                                type="number"
                                wire:model.live.debounce.500ms="descuentoInput"
                                min="0" :max="$totalBase" step="0.01"
                                placeholder="0.00"
                            />
                        </x-filament::input.wrapper>
                    </div>
                    <span class="{{ $descuento > 0 ? 'cp-rf__desc-valor' : '' }}">
                        {{ $descuento > 0 ? '- S/ ' . number_format($descuento, 2) : 'S/ 0.00' }}
                    </span>
                </div>
            </div>

            <div class="cp-resumen-sep"></div>

            @if($esFE)
            <div class="cp-rf cp-rf--sub">
                <span>Subtotal</span>
                <span>S/ {{ number_format($baseImpon, 2) }}</span>
            </div>
            @endif

            <div class="cp-total-bloque">
                <span class="cp-total-bloque__label">Total a pagar</span>
                <span class="cp-total-bloque__valor">S/ {{ number_format($totalDesc, 2) }}</span>
            </div>

            @if(! empty($pagosAgregados))
            <div class="cp-resumen-sep"></div>
            <div class="cp-rf">
                <span>Monto recibido</span>
                <span>S/ {{ number_format($totalPagado, 2) }}</span>
            </div>
            @if($vuelto > 0.005)
            <div class="cp-rf cp-rf--vuelto">
                <span>Vuelto</span>
                <span>S/ {{ number_format($vuelto, 2) }}</span>
            </div>
            <div class="cp-vuelto-nota">
                <x-filament::icon icon="heroicon-o-information-circle" style="width:.85rem;height:.85rem;flex-shrink:0;" />
                <span>El cambio a entregar es S/ {{ number_format($vuelto, 2) }}</span>
            </div>
            @endif
            @endif

            @if(empty($pagosAgregados) && $totalDesc > 0)
            <div class="cp-aviso-info">
                <x-filament::icon icon="heroicon-o-information-circle" style="width:.85rem;height:.85rem;flex-shrink:0;" />
                <span>Agrega el pago antes de confirmar</span>
            </div>
            @endif

            <div class="cp-acciones">
                <x-filament::button
                    color="gray"
                    outlined
                    wire:click="volverAlPedido"
                    style="flex:1;justify-content:center;"
                >
                    Cancelar
                </x-filament::button>

                @can('restaurante.pedido.cobrar')
                <x-filament::button
                    color="primary"
                    icon="heroicon-m-check-circle"
                    wire:click="procesarCobro"
                    wire:loading.attr="disabled"
                    wire:target="procesarCobro"
                    :disabled="! $puedeConfirmar"
                    style="flex:2;justify-content:center;"
                >
                    <span wire:loading.remove wire:target="procesarCobro">✓ Completar venta</span>
                    <span wire:loading wire:target="procesarCobro">Procesando…</span>
                </x-filament::button>
                @endcan
            </div>

        </div>
    </div>

</div>

<script>
(function () {
    var mesasUrl = '{{ \App\Filament\Pdv\Pages\MapaMesasPage::getUrl(tenant: \Filament\Facades\Filament::getTenant()) }}';
    window.addEventListener('modal-impresion-cerrada', function () {
        if (window.Livewire && typeof Livewire.navigate === 'function') {
            Livewire.navigate(mesasUrl);
        } else {
            window.location.href = mesasUrl;
        }
    }, { once: true });
})();
</script>

</x-filament-panels::page>

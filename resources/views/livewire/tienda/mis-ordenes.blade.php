@push('styles')
<link rel="stylesheet" href="{{ asset('tienda/css/mis-ordenes.css') }}">
@endpush

<div class="mo-page">

    {{-- ── Cabecera ──────────────────────────────────────────────────── --}}
    <div class="mo-header">
        <div>
            <h1 class="mo-titulo">Mis órdenes</h1>
            <p class="mo-sub">Historial de pedidos realizados</p>
        </div>
    </div>

    {{-- ── Sin órdenes ───────────────────────────────────────────────── --}}
    @if ($ordenes->isEmpty())
        <div class="mo-vacio">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="mo-vacio-icono">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z"/>
            </svg>
            <p class="mo-vacio-txt">Aún no tienes órdenes registradas</p>
            <a href="{{ route('tienda.catalogo') }}" wire:navigate class="mo-btn-primario">
                Ver catálogo
            </a>
        </div>

    {{-- ── Tabla de órdenes ───────────────────────────────────────────── --}}
    @else

        {{-- Cards (solo mobile) --}}
        <div class="mo-cards">
            @foreach ($ordenes as $orden)
                @php
                    $estado   = $orden->estado;
                    $badgeCss = match ($estado->value) {
                        'pendiente_pago'  => 'mo-badge--warning',
                        'pago_confirmado' => 'mo-badge--success',
                        'cancelada'       => 'mo-badge--danger',
                        default           => 'mo-badge--gray',
                    };
                @endphp
                <div class="mo-card" wire:key="card-{{ $orden->id }}">
                    <div class="mo-card__top">
                        <span class="mo-codigo">{{ $orden->codigo }}</span>
                        <span class="mo-badge {{ $badgeCss }}">{{ $estado->getLabel() }}</span>
                    </div>
                    <div class="mo-card__mid">
                        <span>{{ $orden->fecha_orden->format('d/m/Y') }} · {{ $orden->fecha_orden->format('H:i') }}</span>
                        <span class="mo-card__mid-sep"></span>
                        <span>{{ $orden->detalles_count }} producto{{ $orden->detalles_count !== 1 ? 's' : '' }}</span>
                    </div>
                    <div class="mo-card__bot">
                        <span class="mo-total">S/ {{ number_format($orden->total, 2) }}</span>
                        <button wire:click="abrirDetalle({{ $orden->id }})" class="mo-btn-ver">
                            Ver detalle
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Tabla (desktop) --}}
        <div class="mo-tabla-wrap">
            <table class="mo-tabla">
                <thead>
                    <tr>
                        <th>Orden</th>
                        <th>Fecha</th>
                        <th class="mo-th-centro">Productos</th>
                        <th class="mo-th-right">Total</th>
                        <th class="mo-th-centro">Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ordenes as $orden)
                        @php
                            $estado   = $orden->estado;
                            $badgeCss = match ($estado->value) {
                                'pendiente_pago'  => 'mo-badge--warning',
                                'pago_confirmado' => 'mo-badge--success',
                                'cancelada'       => 'mo-badge--danger',
                                default           => 'mo-badge--gray',
                            };
                        @endphp
                        <tr wire:key="orden-{{ $orden->id }}" class="mo-fila">
                            <td>
                                <span class="mo-codigo">{{ $orden->codigo }}</span>
                            </td>
                            <td>
                                <span class="mo-fecha">{{ $orden->fecha_orden->format('d/m/Y') }}</span>
                                <span class="mo-fecha-hora">{{ $orden->fecha_orden->format('H:i') }}</span>
                            </td>
                            <td class="mo-td-centro">
                                <span class="mo-count-items">{{ $orden->detalles_count }} producto{{ $orden->detalles_count !== 1 ? 's' : '' }}</span>
                            </td>
                            <td class="mo-td-right">
                                <span class="mo-total">S/ {{ number_format($orden->total, 2) }}</span>
                            </td>
                            <td class="mo-td-centro">
                                <span class="mo-badge {{ $badgeCss }}">{{ $estado->getLabel() }}</span>
                            </td>
                            <td>
                                <button wire:click="abrirDetalle({{ $orden->id }})" class="mo-btn-ver">
                                    Ver detalle
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($ordenes->hasPages())
            <div class="mo-paginacion">
                {{ $ordenes->links('livewire.tienda.paginacion') }}
            </div>
        @endif
    @endif

    {{-- ── Modal detalle ─────────────────────────────────────────────── --}}
    @if ($ordenDetalle)
        @php
            $od       = $ordenDetalle;
            $estadoOd = $od->estado;
            $badgeOd  = match ($estadoOd->value) {
                'pendiente_pago'  => 'mo-badge--warning',
                'pago_confirmado' => 'mo-badge--success',
                'cancelada'       => 'mo-badge--danger',
                default           => 'mo-badge--gray',
            };
        @endphp
        <div class="mo-overlay" wire:click.self="cerrarDetalle">
            <div class="mo-modal">

                {{-- Cabecera modal --}}
                <div class="mo-modal__head">
                    <div class="mo-modal__head-info">
                        <span class="mo-modal__codigo">{{ $od->codigo }}</span>
                        <span class="mo-badge {{ $badgeOd }}">{{ $estadoOd->getLabel() }}</span>
                    </div>
                    <button wire:click="cerrarDetalle" class="mo-modal__cerrar" title="Cerrar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                             width="18" height="18">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Info resumen --}}
                <div class="mo-modal__meta">
                    <div class="mo-modal__meta-item">
                        <span class="mo-modal__meta-label">Fecha</span>
                        <span class="mo-modal__meta-val">{{ $od->fecha_orden->format('d/m/Y H:i') }}</span>
                    </div>
                    @if ($od->metodoPago)
                        <div class="mo-modal__meta-item">
                            <span class="mo-modal__meta-label">Pago</span>
                            <span class="mo-modal__meta-val">{{ $od->metodoPago->nombre }}</span>
                        </div>
                    @endif
                    @if ($od->tipo_entrega === 'envio' && $od->metodoEnvio)
                        <div class="mo-modal__meta-item">
                            <span class="mo-modal__meta-label">Envío</span>
                            <span class="mo-modal__meta-val">{{ $od->metodoEnvio->nombre }}</span>
                        </div>
                    @elseif ($od->tipo_entrega === 'recojo')
                        <div class="mo-modal__meta-item">
                            <span class="mo-modal__meta-label">Entrega</span>
                            <span class="mo-modal__meta-val">Recojo en tienda</span>
                        </div>
                    @endif
                    @if ($od->direccion_agencia)
                        <div class="mo-modal__meta-item">
                            <span class="mo-modal__meta-label">Dirección</span>
                            <span class="mo-modal__meta-val">{{ $od->direccion_agencia }}</span>
                        </div>
                    @endif
                </div>

                {{-- Productos --}}
                <div class="mo-modal__body">
                    <p class="mo-modal__section-label">Productos</p>
                    <ul class="mo-modal__items">
                        @foreach ($od->detalles as $d)
                            <li class="mo-modal__item">
                                <span class="mo-modal__item-cant">×{{ rtrim(rtrim(number_format((float)$d->cantidad, 2, '.', ''), '0'), '.') }}</span>
                                <span class="mo-modal__item-desc">{{ $d->descripcion }}</span>
                                <span class="mo-modal__item-total">S/ {{ number_format($d->total, 2) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- Totales --}}
                <div class="mo-modal__totales">
                    @if ((float)$od->costo_envio > 0)
                        <div class="mo-modal__total-fila">
                            <span>Subtotal productos</span>
                            <span>S/ {{ number_format($od->subtotal - (float)$od->costo_envio, 2) }}</span>
                        </div>
                        <div class="mo-modal__total-fila">
                            <span>Costo de envío</span>
                            <span>S/ {{ number_format($od->costo_envio, 2) }}</span>
                        </div>
                    @endif
                    <div class="mo-modal__total-fila mo-modal__total-fila--grande">
                        <span>Total</span>
                        <span>S/ {{ number_format($od->total, 2) }}</span>
                    </div>
                </div>

                {{-- Nota del cliente --}}
                @if ($od->notas)
                    <div class="mo-modal__nota">
                        <span class="mo-modal__meta-label">Nota</span>
                        <p>{{ $od->notas }}</p>
                    </div>
                @endif

            </div>
        </div>
    @endif

</div>

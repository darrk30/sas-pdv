<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/mapa-mesas.css') }}?v={{ filemtime(public_path('css/mapa-mesas.css')) }}">

@php
    $pisos          = $this->getPisos();
    $pisoActivo     = $pisos->firstWhere('id', $this->pisoActivoId) ?? $pisos->first();
    $llevarOrdens   = $this->getLlevarOrdenes();
    $deliveryOrdens = $this->getDeliveryOrdenes();
@endphp

<div class="mm-root">

    {{-- ── Selector de vista: Mesas / Para llevar / Delivery ─────────── --}}
    <div class="mm-vista-tabs">
        <button
            wire:click="cambiarVista('mesas')"
            class="mm-vista-tab mm-vista-tab--mesas {{ $this->vistaActual === 'mesas' ? 'mm-vista-tab--active' : '' }}"
        >
            <x-heroicon-o-building-storefront class="mm-vista-tab-icon" />
            <span class="mm-tab-label">Mesas</span>
        </button>
        <button
            wire:click="cambiarVista('llevar')"
            class="mm-vista-tab mm-vista-tab--llevar {{ $this->vistaActual === 'llevar' ? 'mm-vista-tab--active' : '' }}"
        >
            <x-heroicon-o-shopping-bag class="mm-vista-tab-icon" />
            <span class="mm-tab-label">
                <span class="mm-tab-label-full">Para llevar</span>
                <span class="mm-tab-label-short">Llevar</span>
            </span>
            @if($llevarOrdens->count() > 0)
                <span class="mm-vista-tab-badge">{{ $llevarOrdens->count() }}</span>
            @endif
        </button>
        <button
            wire:click="cambiarVista('delivery')"
            class="mm-vista-tab mm-vista-tab--delivery {{ $this->vistaActual === 'delivery' ? 'mm-vista-tab--active' : '' }}"
        >
            <x-heroicon-o-truck class="mm-vista-tab-icon" />
            <span class="mm-tab-label">Delivery</span>
            @if($deliveryOrdens->count() > 0)
                <span class="mm-vista-tab-badge mm-vista-tab-badge--delivery">{{ $deliveryOrdens->count() }}</span>
            @endif
        </button>
    </div>

    {{-- ══ VISTA: PARA LLEVAR ══════════════════════════════════════════ --}}
    @if($this->vistaActual === 'llevar')
        <div class="mm-llevar-header">
            @can('restaurante.pedido.crear')
            <button
                wire:click="iniciarPedidoLlevar"
                wire:loading.attr="disabled"
                wire:target="iniciarPedidoLlevar"
                class="mm-btn mm-btn--abrir"
            >
                <x-heroicon-m-plus-circle class="mm-btn-icon" />
                <span wire:loading.remove wire:target="iniciarPedidoLlevar">Nueva orden para llevar</span>
                <span wire:loading wire:target="iniciarPedidoLlevar">Cargando…</span>
            </button>
            @endcan
        </div>

        @if($llevarOrdens->isEmpty())
            <div class="mm-empty">
                <div class="mm-empty-illus">
                    <x-heroicon-o-shopping-bag style="width:3.5rem;height:3.5rem;color:#c4b5fd;" />
                </div>
                <p class="mm-empty-title">No hay pedidos para llevar</p>
                <p class="mm-empty-sub">Crea una nueva orden para llevar con el botón de arriba.</p>
            </div>
        @else
            <div class="mm-ord-grid">
                @foreach($llevarOrdens as $lo)
                    @php
                        $minutos   = (int) $lo->created_at->diffInMinutes(now());
                        $horas     = intdiv($minutos, 60);
                        $mins      = $minutos % 60;
                        $tiempoStr = $horas > 0 ? "{$horas}h {$mins}m" : "{$minutos}m";
                        $loCobrado = $lo->venta_id !== null;
                        $loEstado  = $lo->estado instanceof \App\Enums\EstadoOrden ? $lo->estado->value : $lo->estado;
                        $urgencia  = $minutos >= 30 ? 'urgente' : ($minutos >= 15 ? 'alerta' : 'normal');
                    @endphp
                    <div class="mm-ord-card mm-ord-card--llevar mm-ord-card--{{ $urgencia }}{{ $loop->first ? ' mm-ord-card--primero' : '' }}{{ $loEstado === 'en_preparacion' ? ' mm-ord-card--en-prep' : '' }}">
                        <div class="mm-ord-top">
                            <span class="mm-ord-pos">{{ $loop->iteration }}</span>
                            <span class="mm-ord-tiempo mm-ord-tiempo--{{ $urgencia }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mm-ord-clock"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                                {{ $tiempoStr }}
                            </span>
                        </div>
                        <div class="mm-ord-body">
                            <div class="mm-ord-icon mm-ord-icon--llevar">
                                <x-heroicon-o-shopping-bag style="width:1.1rem;height:1.1rem;" />
                            </div>
                            <span class="mm-ord-nombre">{{ $lo->cliente_nombre ?: 'Sin nombre' }}</span>
                        </div>
                        <div class="mm-ord-meta">
                            <span class="mm-ord-codigo">#{{ $lo->codigo }}</span>
                            <span>{{ $lo->created_at->format('H:i') }}</span>
                            @if($lo->total > 0)<span class="mm-ord-total">S/ {{ number_format($lo->total, 2) }}</span>@endif
                        </div>
                        <span class="mm-ord-badge mm-ord-badge--{{ $loEstado }}">
                            {{ $lo->estado instanceof \App\Enums\EstadoOrden ? $lo->estado->getLabel() : $loEstado }}
                            @if($loCobrado) · Pagado @endif
                        </span>
                        <div class="mm-ord-actions">
                            @can('restaurante.pedido.editar')
                            @if($loCobrado)
                            <button wire:click="abrirDetalleOrden({{ $lo->id }})" class="mm-btn mm-btn--ver mm-btn--sm">
                                <x-heroicon-m-clipboard-document-list class="mm-btn-icon" /><span class="mm-btn-label">Ver</span>
                            </button>
                            @else
                            <a href="{{ \App\Filament\Pdv\Resources\OrdenRest\OrdenRestResource::getUrl('edit', ['record' => $lo->id], tenant: filament()->getTenant()) }}" class="mm-btn mm-btn--ver mm-btn--sm" wire:navigate>
                                <x-heroicon-m-clipboard-document-list class="mm-btn-icon" /><span class="mm-btn-label">Ver</span>
                            </a>
                            @endif
                            @endcan
                            @if($loCobrado)
                                @can('restaurante.pedido.editar')
                                <button wire:click="marcarEntregado({{ $lo->id }})" wire:loading.attr="disabled" wire:target="marcarEntregado({{ $lo->id }})" class="mm-btn mm-btn--abrir mm-btn--sm">
                                    <x-heroicon-m-check-circle class="mm-btn-icon" /><span class="mm-btn-label">Entregar</span>
                                </button>
                                @endcan
                                @can('restaurante.pedido.eliminar')
                                <button wire:click="mountAction('cancelarOrdenPagada', {ordenId: {{ $lo->id }}})" wire:loading.attr="disabled" wire:target="mountAction" class="mm-btn mm-btn--cancelar mm-btn--sm">
                                    <x-heroicon-m-x-mark class="mm-btn-icon" /><span class="mm-btn-label">Cancelar</span>
                                </button>
                                @endcan
                            @else
                                @can('restaurante.pedido.cobrar')
                                <a href="{{ \App\Filament\Pdv\Resources\OrdenRest\OrdenRestResource::getUrl('cobrar', ['record' => $lo->id], tenant: filament()->getTenant()) }}" class="mm-btn mm-btn--pagar mm-btn--sm" wire:navigate>
                                    <x-heroicon-m-banknotes class="mm-btn-icon" /><span class="mm-btn-label">Cobrar</span>
                                </a>
                                @endcan
                                @can('restaurante.pedido.eliminar')
                                <button wire:click="mountAction('cancelarOrden', {ordenId: {{ $lo->id }}})" wire:loading.attr="disabled" wire:target="mountAction" class="mm-btn mm-btn--cancelar mm-btn--sm">
                                    <x-heroicon-m-x-mark class="mm-btn-icon" /><span class="mm-btn-label">Cancelar</span>
                                </button>
                                @endcan
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

    {{-- ══ VISTA: DELIVERY ═══════════════════════════════════════════ --}}
    @elseif($this->vistaActual === 'delivery')
        <div class="mm-llevar-header">
            @can('restaurante.pedido.crear')
            <button
                wire:click="iniciarPedidoDelivery"
                wire:loading.attr="disabled"
                wire:target="iniciarPedidoDelivery"
                class="mm-btn mm-btn--delivery-new"
            >
                <x-heroicon-m-plus-circle class="mm-btn-icon" />
                <span wire:loading.remove wire:target="iniciarPedidoDelivery">Nueva orden delivery</span>
                <span wire:loading wire:target="iniciarPedidoDelivery">Cargando…</span>
            </button>
            @endcan
        </div>

        @if($deliveryOrdens->isEmpty())
            <div class="mm-empty">
                <div class="mm-empty-illus">
                    <x-heroicon-o-truck style="width:3.5rem;height:3.5rem;color:#c4b5fd;" />
                </div>
                <p class="mm-empty-title">No hay pedidos delivery</p>
                <p class="mm-empty-sub">Crea una nueva orden delivery con el botón de arriba.</p>
            </div>
        @else
            <div class="mm-ord-grid">
                @foreach($deliveryOrdens as $do)
                    @php
                        $minutos   = (int) $do->created_at->diffInMinutes(now());
                        $horas     = intdiv($minutos, 60);
                        $mins      = $minutos % 60;
                        $tiempoStr = $horas > 0 ? "{$horas}h {$mins}m" : "{$minutos}m";
                        $doCobrado = $do->venta_id !== null;
                        $doEstado  = $do->estado instanceof \App\Enums\EstadoOrden ? $do->estado->value : $do->estado;
                        $doEnPrep  = in_array($doEstado, ['pendiente_pago', 'en_preparacion']);
                        $urgencia  = $minutos >= 30 ? 'urgente' : ($minutos >= 15 ? 'alerta' : 'normal');
                        $repNombre = null;
                        if ($do->repartidor) {
                            $repNombre = $do->repartidor->name;
                        } elseif ($do->notas_internas) {
                            $ni = json_decode($do->notas_internas, true);
                            $repNombre = $ni['repartidor'] ?? null;
                        }
                    @endphp
                    <div class="mm-ord-card mm-ord-card--delivery mm-ord-card--{{ $urgencia }}{{ $loop->first ? ' mm-ord-card--primero' : '' }}{{ $doEstado === 'en_preparacion' ? ' mm-ord-card--en-prep mm-ord-card--en-prep-delivery' : '' }}">
                        <div class="mm-ord-top">
                            <span class="mm-ord-pos mm-ord-pos--delivery">{{ $loop->iteration }}</span>
                            <span class="mm-ord-tiempo mm-ord-tiempo--{{ $urgencia }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mm-ord-clock"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                                {{ $tiempoStr }}
                            </span>
                        </div>
                        <div class="mm-ord-body">
                            <div class="mm-ord-icon mm-ord-icon--delivery">
                                <x-heroicon-o-truck style="width:1.1rem;height:1.1rem;" />
                            </div>
                            <span class="mm-ord-nombre">{{ $do->cliente_nombre ?: 'Sin nombre' }}</span>
                        </div>
                        <div class="mm-ord-meta">
                            <span class="mm-ord-codigo">#{{ $do->codigo }}</span>
                            <span>{{ $do->created_at->format('H:i') }}</span>
                            @if($do->total > 0)<span class="mm-ord-total">S/ {{ number_format($do->total, 2) }}</span>@endif
                        </div>
                        @if($do->cliente_telefono || $do->cliente_direccion || $repNombre)
                            <div class="mm-ord-extra">
                                @if($do->cliente_telefono)
                                    <span class="mm-ord-extra-row">
                                        <x-heroicon-m-phone style="width:.7rem;height:.7rem;flex-shrink:0;" />
                                        {{ $do->cliente_telefono }}
                                    </span>
                                @endif
                                @if($do->cliente_direccion)
                                    <span class="mm-ord-extra-row">
                                        <x-heroicon-m-map-pin style="width:.7rem;height:.7rem;flex-shrink:0;" />
                                        {{ $do->cliente_direccion }}
                                    </span>
                                @endif
                                @if($repNombre)
                                    <span class="mm-ord-extra-row">
                                        <x-heroicon-m-user style="width:.7rem;height:.7rem;flex-shrink:0;" />
                                        {{ $repNombre }}
                                    </span>
                                @endif
                            </div>
                        @endif
                        <span class="mm-ord-badge mm-ord-badge--{{ $doEstado }}">
                            {{ $do->estado instanceof \App\Enums\EstadoOrden ? $do->estado->getLabel() : $doEstado }}
                            @if($doCobrado) · Pagado @endif
                        </span>
                        <div class="mm-ord-actions">
                            @can('restaurante.pedido.editar')
                            @if($doCobrado)
                            <button wire:click="abrirDetalleOrden({{ $do->id }})" class="mm-btn mm-btn--ver mm-btn--sm">
                                <x-heroicon-m-clipboard-document-list class="mm-btn-icon" /><span class="mm-btn-label">Ver</span>
                            </button>
                            @else
                            <a href="{{ \App\Filament\Pdv\Resources\OrdenRest\OrdenRestResource::getUrl('edit', ['record' => $do->id], tenant: filament()->getTenant()) }}" class="mm-btn mm-btn--ver mm-btn--sm" wire:navigate>
                                <x-heroicon-m-clipboard-document-list class="mm-btn-icon" /><span class="mm-btn-label">Ver</span>
                            </a>
                            @endif
                            @endcan
                            @if($doEnPrep)
                                @can('restaurante.pedido.editar')
                                <button wire:click="marcarEnCamino({{ $do->id }})" wire:loading.attr="disabled" wire:target="marcarEnCamino({{ $do->id }})" class="mm-btn mm-btn--encamino mm-btn--sm">
                                    <x-heroicon-m-truck class="mm-btn-icon" /><span class="mm-btn-label">En camino</span>
                                </button>
                                @endcan
                            @endif
                            @if($doCobrado)
                                @can('restaurante.pedido.editar')
                                <button wire:click="marcarEntregado({{ $do->id }})" wire:loading.attr="disabled" wire:target="marcarEntregado({{ $do->id }})" class="mm-btn mm-btn--abrir mm-btn--sm">
                                    <x-heroicon-m-check-circle class="mm-btn-icon" /><span class="mm-btn-label">Entregar</span>
                                </button>
                                @endcan
                                @can('restaurante.pedido.eliminar')
                                <button wire:click="mountAction('cancelarOrdenPagada', {ordenId: {{ $do->id }}})" wire:loading.attr="disabled" wire:target="mountAction" class="mm-btn mm-btn--cancelar mm-btn--sm">
                                    <x-heroicon-m-x-mark class="mm-btn-icon" /><span class="mm-btn-label">Cancelar</span>
                                </button>
                                @endcan
                            @else
                                @can('restaurante.pedido.cobrar')
                                <a href="{{ \App\Filament\Pdv\Resources\OrdenRest\OrdenRestResource::getUrl('cobrar', ['record' => $do->id], tenant: filament()->getTenant()) }}" class="mm-btn mm-btn--pagar mm-btn--sm" wire:navigate>
                                    <x-heroicon-m-banknotes class="mm-btn-icon" /><span class="mm-btn-label">Cobrar</span>
                                </a>
                                @endcan
                                @can('restaurante.pedido.eliminar')
                                <button wire:click="mountAction('cancelarOrden', {ordenId: {{ $do->id }}})" wire:loading.attr="disabled" wire:target="mountAction" class="mm-btn mm-btn--cancelar mm-btn--sm">
                                    <x-heroicon-m-x-mark class="mm-btn-icon" /><span class="mm-btn-label">Cancelar</span>
                                </button>
                                @endcan
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

    {{-- ══ VISTA: MESAS ════════════════════════════════════════════════ --}}
    @else

    {{-- ── Sin pisos ──────────────────────────────────────────────────── --}}
    @if($pisos->isEmpty())
        <div class="mm-empty">
            <div class="mm-empty-illus">
                <svg viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="40" cy="40" r="30" fill="#ede9fe" opacity=".7"/>
                    <circle cx="40" cy="40" r="18" stroke="#8b5cf6" stroke-width="2.5" fill="#ddd6fe" opacity=".6"/>
                    <circle cx="40" cy="15" r="5" fill="#8b5cf6" opacity=".5"/>
                    <circle cx="40" cy="65" r="5" fill="#8b5cf6" opacity=".5"/>
                    <circle cx="15" cy="40" r="5" fill="#8b5cf6" opacity=".5"/>
                    <circle cx="65" cy="40" r="5" fill="#8b5cf6" opacity=".5"/>
                </svg>
            </div>
            <p class="mm-empty-title">No hay pisos configurados</p>
            <p class="mm-empty-sub">Ve a <strong>Restaurante → Pisos y Mesas</strong> para crear tu primer piso y sus mesas.</p>
        </div>
    @else

        {{-- ── Tabs de pisos ───────────────────────────────────────────── --}}
        @if($pisos->count() > 1)
            <div class="mm-tabs">
                @foreach($pisos as $piso)
                    <button
                        wire:click="seleccionarPiso({{ $piso->id }})"
                        class="mm-tab {{ $pisoActivo?->id === $piso->id ? 'mm-tab--active' : '' }}"
                    >
                        <x-heroicon-o-building-storefront class="mm-tab-icon" />
                        {{ $piso->nombre }}
                        <span class="mm-tab-count">{{ $piso->mesas->count() }}</span>
                    </button>
                @endforeach
            </div>
        @endif

        {{-- ── Leyenda ─────────────────────────────────────────────────── --}}
        <div class="mm-leyenda">
            <span class="mm-leyenda-item"><span class="mm-dot mm-dot--libre"></span>Libre</span>
            <span class="mm-leyenda-item"><span class="mm-dot mm-dot--ocupada"></span>Ocupada</span>
            <span class="mm-leyenda-item"><span class="mm-dot mm-dot--pagando"></span>Pagando</span>
            @if($pisoActivo)
                <span class="mm-leyenda-sep">·</span>
                <span class="mm-leyenda-piso">{{ $pisoActivo->nombre }}</span>
                @if($pisoActivo->impresora)
                    <span class="mm-leyenda-impresora">
                        <x-heroicon-m-printer class="mm-leyenda-icon" />
                        {{ $pisoActivo->impresora->nombre }}
                    </span>
                @endif
            @endif
            <span class="mm-leyenda-sep">·</span>
            <span class="mm-leyenda-refresh">
                <x-heroicon-m-arrow-path class="mm-leyenda-icon" wire:loading />
                Actualiza cada 30s
            </span>
        </div>

        {{-- ── Grid de mesas ────────────────────────────────────────────── --}}
        @if($pisoActivo)
            @php $mesas = $pisoActivo->mesas; @endphp

            @if($mesas->isEmpty())
                <div class="mm-empty">
                    <div class="mm-empty-illus">
                        <x-heroicon-o-square-3-stack-3d style="width:3.5rem;height:3.5rem;color:#c4b5fd;" />
                    </div>
                    <p class="mm-empty-title">Este piso no tiene mesas</p>
                    <p class="mm-empty-sub">Edita el piso para agregar mesas.</p>
                </div>
            @else
                <div class="mm-grid">
                    @foreach($mesas as $mesa)
                        @php
                            $ocupacion = $mesa->estado_ocupacion->value;
                            $orden     = $mesa->ordenActiva;
                            // diffInMinutes(now) = now - created_at → siempre positivo para órdenes pasadas
                            $minutos   = $orden ? (int) $orden->created_at->diffInMinutes(now()) : null;
                            $horas     = $minutos !== null ? intdiv($minutos, 60) : null;
                            $mins      = $minutos !== null ? $minutos % 60 : null;
                            $tiempoStr = $horas > 0
                                ? "{$horas}h {$mins}m"
                                : ($minutos !== null ? "{$minutos}m" : null);
                        @endphp

                        <div class="mm-card mm-card--{{ $ocupacion }}">

                            {{-- Stripe de color --}}
                            <div class="mm-stripe"></div>

                            <div class="mm-card-body">

                                {{-- Ícono de mesa + nombre + badge --}}
                                <div class="mm-card-header">
                                    {{-- Ícono SVG: mesa redonda vista desde arriba --}}
                                    <div class="mm-table-wrap">
                                        <svg class="mm-table-svg" viewBox="0 0 44 44" fill="none">
                                            <circle cx="22" cy="22" r="13" class="mm-t-top"/>
                                            <circle cx="22" cy="4"  r="4" class="mm-t-chair"/>
                                            <circle cx="22" cy="40" r="4" class="mm-t-chair"/>
                                            <circle cx="4"  cy="22" r="4" class="mm-t-chair"/>
                                            <circle cx="40" cy="22" r="4" class="mm-t-chair"/>
                                        </svg>
                                    </div>
                                    <div class="mm-card-info-head">
                                        <span class="mm-card-nombre">{{ $mesa->nombre }}</span>
                                        <span class="mm-badge mm-badge--{{ $ocupacion }}">
                                            {{ $mesa->estado_ocupacion->getLabel() }}
                                        </span>
                                    </div>
                                </div>

                                {{-- Fila compacta: capacidad + tiempo + total --}}
                                <div class="mm-card-data">
                                    <span class="mm-data-item">
                                        <x-heroicon-m-user-group class="mm-data-icon" />
                                        {{ $mesa->capacidad }}
                                    </span>
                                    @if($ocupacion !== 'libre' && $orden)
                                        @if($tiempoStr)
                                            <span class="mm-data-sep">·</span>
                                            <span class="mm-data-item">
                                                <x-heroicon-m-clock class="mm-data-icon" />
                                                {{ $tiempoStr }}
                                            </span>
                                        @endif
                                        @if($orden->total > 0)
                                            <span class="mm-data-sep">·</span>
                                            <span class="mm-data-item mm-data-total">
                                                <x-heroicon-m-banknotes class="mm-data-icon" />
                                                S/ {{ number_format($orden->total, 2) }}
                                            </span>
                                        @else
                                            <span class="mm-data-sep">·</span>
                                            <span class="mm-data-item mm-data-empty">Sin productos</span>
                                        @endif
                                    @endif
                                </div>

                                {{-- Acciones --}}
                                <div class="mm-card-actions">
                                    @if($ocupacion === 'libre')
                                        @can('restaurante.pedido.crear')
                                        <button
                                            wire:click="iniciarPedido({{ $mesa->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="iniciarPedido({{ $mesa->id }})"
                                            class="mm-btn mm-btn--abrir"
                                        >
                                            <x-heroicon-m-plus-circle class="mm-btn-icon" />
                                            <span wire:loading.remove wire:target="iniciarPedido({{ $mesa->id }})">Iniciar pedido</span>
                                            <span wire:loading wire:target="iniciarPedido({{ $mesa->id }})">Cargando…</span>
                                        </button>
                                        @endcan

                                    @elseif($ocupacion === 'ocupada')
                                        @can('restaurante.pedido.editar')
                                        @if($orden)
                                            <a
                                                href="{{ \App\Filament\Pdv\Resources\OrdenRest\OrdenRestResource::getUrl('edit', ['record' => $orden->id], tenant: filament()->getTenant()) }}"
                                                class="mm-btn mm-btn--ver"
                                                wire:navigate
                                            >
                                                <x-heroicon-m-clipboard-document-list class="mm-btn-icon" />
                                                Ver pedido
                                            </a>
                                        @endif
                                        @endcan
                                        @can('restaurante.pedido.cobrar')
                                        <a
                                            href="{{ $orden ? \App\Filament\Pdv\Resources\OrdenRest\OrdenRestResource::getUrl('cobrar', ['record' => $orden->id], tenant: filament()->getTenant()) : '#' }}"
                                            class="mm-btn mm-btn--pagar"
                                            wire:navigate
                                        >
                                            <x-heroicon-m-banknotes class="mm-btn-icon" />
                                            Cobrar
                                        </a>
                                        @endcan

                                    @elseif($ocupacion === 'pagando')
                                        @can('restaurante.pedido.editar')
                                        @if($orden)
                                            <a
                                                href="{{ \App\Filament\Pdv\Resources\OrdenRest\OrdenRestResource::getUrl('edit', ['record' => $orden->id], tenant: filament()->getTenant()) }}"
                                                class="mm-btn mm-btn--ver"
                                                wire:navigate
                                            >
                                                <x-heroicon-m-clipboard-document-list class="mm-btn-icon" />
                                                Ver pedido
                                            </a>
                                        @endif
                                        @endcan
                                        @can('restaurante.pedido.cobrar')
                                        <a
                                            href="{{ $orden ? \App\Filament\Pdv\Resources\OrdenRest\OrdenRestResource::getUrl('cobrar', ['record' => $orden->id], tenant: filament()->getTenant()) : '#' }}"
                                            class="mm-btn mm-btn--pagar"
                                            wire:navigate
                                        >
                                            <x-heroicon-m-banknotes class="mm-btn-icon" />
                                            Cobrar
                                        </a>
                                        @endcan
                                    @endif
                                </div>

                            </div>{{-- /.mm-card-body --}}
                        </div>
                    @endforeach
                </div>
            @endif
        @endif

    @endif

    @endif {{-- fin @else (vista mesas) --}}

</div>

{{-- ════════════════════════════════════════════════════════════
     MODAL DETALLE ORDEN PAGADA
     ════════════════════════════════════════════════════════════ --}}
@if($this->detalleOrdenId !== null)
    @php
        $detOrden = \App\Models\Orden::with(['detalles.producto', 'venta.serie'])
            ->find($this->detalleOrdenId);
        $detEsDelivery = $this->detalleOrdenTipo === 'delivery';
    @endphp
    @if($detOrden)
    <div class="mm-detalle-wrap">
        <div class="mm-detalle-backdrop" wire:click="cerrarDetalleOrden"></div>
        <div class="mm-detalle-modal">

            {{-- Header --}}
            <div class="mm-detalle-header">
                <div>
                    <h3 class="mm-detalle-titulo">
                        Pedido #{{ $detOrden->codigo }}
                    </h3>
                    <p class="mm-detalle-sub">
                        {{ $detOrden->estado instanceof \App\Enums\EstadoOrden ? $detOrden->estado->getLabel() : $detOrden->estado }}
                        &nbsp;·&nbsp; Pagado
                        @if($detOrden->venta)
                            &nbsp;·&nbsp; {{ ($detOrden->venta->serie?->serie ?? '---') }}-{{ $detOrden->venta->correlativo }}
                        @endif
                    </p>
                </div>
                <button type="button" class="mm-detalle-close" wire:click="cerrarDetalleOrden">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Tabla de productos --}}
            <table class="mm-detalle-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th class="mm-dt-num">Cant.</th>
                        <th class="mm-dt-num">P.Unit</th>
                        <th class="mm-dt-num">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($detOrden->detalles as $det)
                    <tr>
                        <td>{{ $det->descripcion ?? $det->producto?->nombre ?? '—' }}</td>
                        <td class="mm-dt-num">{{ number_format($det->cantidad, 0) }}</td>
                        <td class="mm-dt-num">S/ {{ number_format($det->precio_unitario, 2) }}</td>
                        <td class="mm-dt-num">S/ {{ number_format($det->total ?? ($det->precio_unitario * $det->cantidad), 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3">Total</td>
                        <td class="mm-dt-num">S/ {{ number_format($detOrden->total, 2) }}</td>
                    </tr>
                </tfoot>
            </table>

            {{-- Datos delivery editables --}}
            @if($detEsDelivery)
            <div class="mm-detalle-section">
                <p class="mm-detalle-section-title">Datos de entrega</p>
                <div class="mm-detalle-field">
                    <label>Nombre del cliente</label>
                    <input type="text" wire:model.live="detalleDeliveryNombre" placeholder="Sin nombre" />
                </div>
                <div class="mm-detalle-field">
                    <label>Teléfono</label>
                    <input type="text" wire:model.live="detalleDeliveryTelefono" placeholder="Sin teléfono" />
                </div>
                <div class="mm-detalle-field">
                    <label>Dirección</label>
                    <input type="text" wire:model.live="detalleDeliveryDireccion" placeholder="Sin dirección" />
                </div>
                <div class="mm-detalle-field">
                    <label>Repartidor</label>
                    <select wire:model.live="detalleRepartidorId">
                        <option value="">Sin asignar</option>
                        @foreach($this->getUsuariosRepartidor() as $usr)
                        <option value="{{ $usr->id }}">{{ $usr->name }}</option>
                        @endforeach
                    </select>
                </div>
                @can('restaurante.pedido.editar')
                <button
                    type="button"
                    wire:click="guardarDatosDelivery"
                    class="mm-btn mm-btn--pagar"
                    style="width:100%;justify-content:center;margin-top:.5rem;"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1rem;height:1rem;flex-shrink:0;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z"/>
                    </svg>
                    Guardar datos
                </button>
                @endcan
            </div>
            @endif

        </div>
    </div>
    @endif
@endif

{{-- ════════════════════════════════════════════════════════════
     MODAL COMANDA (Alpine.js — igual al de edit-pedido)
     ════════════════════════════════════════════════════════════ --}}
<div
    x-data="{
        open: false,
        activeTab: 0,
        areas: [],
        ordenId: 0,
        mesa: '',
        cajero: '',
        rol: '',
        numero: '',
        parcial: false,
        descripcion: '',
        ticketBase: '{{ url('/ticket/comanda') }}'
    }"
    @imprimir-comanda-browser.window="
        const raw = $event.detail;
        const d   = (Array.isArray(raw) ? raw[0] : raw) || {};
        ordenId     = d.ordenId     || 0;
        mesa        = d.mesa        || '';
        cajero      = d.cajero      || '';
        rol         = d.rol         || '';
        numero      = d.numero      || '';
        parcial     = !!d.parcial;
        descripcion = d.descripcion || '';
        try { areas = JSON.parse(d.areasJson || '[]'); } catch(e) { areas = []; }
        if (areas.length > 0) { activeTab = 0; open = true; }
    "
    style="display:contents"
>
    <template x-if="open">
        <div class="mm-comanda-overlay">
            <div class="mm-comanda-backdrop" @click="open = false"></div>
            <div class="mm-comanda-modal">

                {{-- Header --}}
                <div class="mm-comanda-header">
                    <div>
                        <h3 class="mm-comanda-titulo" x-text="'Comanda — ' + mesa"></h3>
                        <p class="mm-comanda-sub" x-text="areas.length + (areas.length === 1 ? ' área de producción' : ' áreas de producción')"></p>
                    </div>
                    <button type="button" class="mm-comanda-close" @click="open = false">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Pestañas --}}
                <template x-if="areas.length > 1">
                    <div class="mm-comanda-tabs">
                        <template x-for="(area, i) in areas" :key="'mmt-'+i">
                            <button
                                type="button"
                                class="mm-comanda-tab"
                                :class="activeTab === i ? 'mm-comanda-tab--active' : ''"
                                @click="activeTab = i"
                                x-text="area.nombre ? area.nombre.toUpperCase() : ''"
                            ></button>
                        </template>
                    </div>
                </template>

                {{-- Paneles --}}
                <div class="mm-comanda-body">
                    <template x-for="(area, i) in areas" :key="'mmp-'+i">
                        <div x-show="activeTab === i">
                            <div class="mm-comanda-chips" x-show="(area.nuevos && area.nuevos.length) || (area.cancelados && area.cancelados.length)" style="display:flex">
                                <span class="mm-comanda-chip mm-comanda-chip--nuevo"
                                    x-show="area.nuevos && area.nuevos.length"
                                    x-text="'+' + (area.nuevos ? area.nuevos.reduce((s,i)=>s+i.cant,0) : 0) + ' agregar'"></span>
                                <span class="mm-comanda-chip mm-comanda-chip--quitar"
                                    x-show="area.cancelados && area.cancelados.length"
                                    x-text="'-' + (area.cancelados ? area.cancelados.reduce((s,i)=>s+i.cant,0) : 0) + ' quitar'"></span>
                            </div>
                            <div class="mm-comanda-iframe-wrap">
                                <iframe
                                    :id="'mm-frame-' + i"
                                    :src="ticketBase + '/' + ordenId + '?' + new URLSearchParams({
                                        area_nombre: area.nombre || 'COCINA',
                                        nuevos:      JSON.stringify(area.nuevos     || []),
                                        cancelados:  JSON.stringify(area.cancelados || []),
                                        notas:       JSON.stringify(area.notas      || []),
                                        descripcion: descripcion,
                                        parcial:     parcial ? '1' : '0',
                                        mesa:        mesa,
                                        cajero:      cajero,
                                        rol:         rol,
                                        numero:      numero
                                    }).toString()"
                                    class="mm-comanda-iframe"
                                    @load="
                                        try {
                                            const doc = $el.contentDocument || $el.contentWindow.document;
                                            const h = doc.documentElement.scrollHeight || doc.body.scrollHeight;
                                            if (h > 10) $el.style.height = h + 'px';
                                        } catch(e) {}
                                    "
                                ></iframe>
                            </div>
                            <button
                                type="button"
                                class="mm-comanda-print-btn"
                                @click="document.getElementById('mm-frame-' + i).contentWindow.print()"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1rem;height:1rem;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/></svg>
                                <span x-text="'Imprimir ' + (area.nombre ? area.nombre.toUpperCase() : '')"></span>
                            </button>
                        </div>
                    </template>
                </div>

            </div>
        </div>
    </template>
</div>

<x-filament-actions::modals />


</x-filament-panels::page>

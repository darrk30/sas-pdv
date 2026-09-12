<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/mapa-mesas.css') }}?v={{ filemtime(public_path('css/mapa-mesas.css')) }}">

@php
    $pisos      = $this->getPisos();
    $pisoActivo = $pisos->firstWhere('id', $this->pisoActivoId) ?? $pisos->first();
@endphp

<div class="mm-root">

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

                                {{-- Capacidad --}}
                                <div class="mm-card-cap">
                                    <x-heroicon-m-user-group class="mm-cap-icon" />
                                    {{ $mesa->capacidad }} personas
                                </div>

                                {{-- Info si está ocupada o pagando --}}
                                @if($ocupacion !== 'libre' && $orden)
                                    <div class="mm-card-data">
                                        @if($tiempoStr)
                                            <div class="mm-data-row">
                                                <x-heroicon-m-clock class="mm-data-icon" />
                                                <span>{{ $tiempoStr }}</span>
                                            </div>
                                        @endif
                                        @if($orden->total > 0)
                                            <div class="mm-data-row mm-data-total">
                                                <x-heroicon-m-banknotes class="mm-data-icon" />
                                                <span>S/ {{ number_format($orden->total, 2) }}</span>
                                            </div>
                                        @else
                                            <div class="mm-data-row mm-data-empty">
                                                <x-heroicon-m-pencil-square class="mm-data-icon" />
                                                <span>Sin productos aún</span>
                                            </div>
                                        @endif
                                    </div>
                                @endif

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
                                        <button
                                            wire:click="liberarMesa({{ $mesa->id }})"
                                            wire:confirm="¿Liberar la mesa sin cobrar? Esto cancelará el pedido si está vacío."
                                            class="mm-btn mm-btn--liberar"
                                        >
                                            <x-heroicon-m-x-circle class="mm-btn-icon" />
                                            Liberar mesa
                                        </button>
                                    @endif
                                </div>

                            </div>{{-- /.mm-card-body --}}
                        </div>
                    @endforeach
                </div>
            @endif
        @endif

    @endif

</div>

</x-filament-panels::page>

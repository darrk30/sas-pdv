<x-filament-panels::page>

@php
    $pisos = $this->getPisos();
    $pisoActivo = $pisos->firstWhere('id', $this->pisoActivoId) ?? $pisos->first();
@endphp

<div class="mm-root" wire:poll.30s>

    {{-- ── Sin pisos ───────────────────────────────────────────────── --}}
    @if($pisos->isEmpty())
        <div class="mm-empty">
            <x-heroicon-o-building-storefront class="mm-empty-icon" />
            <p class="mm-empty-title">No hay pisos configurados</p>
            <p class="mm-empty-sub">Ve a <strong>Restaurante → Pisos y Mesas</strong> para crear tu primer piso y sus mesas.</p>
        </div>
    @else

        {{-- ── Tabs de pisos ───────────────────────────────────────── --}}
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

        {{-- ── Leyenda ─────────────────────────────────────────────── --}}
        <div class="mm-leyenda">
            <span class="mm-leyenda-item">
                <span class="mm-dot mm-dot--libre"></span> Libre
            </span>
            <span class="mm-leyenda-item">
                <span class="mm-dot mm-dot--ocupada"></span> Ocupada
            </span>
            <span class="mm-leyenda-item">
                <span class="mm-dot mm-dot--pagando"></span> Pagando
            </span>
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
                <x-heroicon-m-arrow-path class="mm-leyenda-icon" wire:loading /> Actualiza cada 30s
            </span>
        </div>

        {{-- ── Grid de mesas ───────────────────────────────────────── --}}
        @if($pisoActivo)
            @php $mesas = $pisoActivo->mesas; @endphp

            @if($mesas->isEmpty())
                <div class="mm-empty">
                    <x-heroicon-o-square-3-stack-3d class="mm-empty-icon" />
                    <p class="mm-empty-title">Este piso no tiene mesas</p>
                    <p class="mm-empty-sub">Edita el piso para agregar mesas.</p>
                </div>
            @else
                <div class="mm-grid">
                    @foreach($mesas as $mesa)
                        @php
                            $ocupacion = $mesa->estado_ocupacion->value;
                            $orden     = $mesa->ordenActiva;
                            $minutos   = $orden
                                ? (int) now()->diffInMinutes($orden->created_at)
                                : null;
                            $horas     = $minutos !== null ? intdiv($minutos, 60) : null;
                            $mins      = $minutos !== null ? $minutos % 60 : null;
                            $tiempoStr = $horas > 0
                                ? "{$horas}h {$mins}m"
                                : ($minutos !== null ? "{$minutos}m" : null);
                        @endphp

                        <div class="mm-card mm-card--{{ $ocupacion }}">

                            {{-- Cabecera --}}
                            <div class="mm-card-header">
                                <span class="mm-card-nombre">{{ $mesa->nombre }}</span>
                                <span class="mm-badge mm-badge--{{ $ocupacion }}">
                                    {{ $mesa->estado_ocupacion->getLabel() }}
                                </span>
                            </div>

                            {{-- Capacidad --}}
                            <div class="mm-card-cap">
                                <x-heroicon-m-user-group class="mm-cap-icon" />
                                {{ $mesa->capacidad }} personas
                            </div>

                            {{-- Info si está ocupada --}}
                            @if($ocupacion !== 'libre' && $orden)
                                <div class="mm-card-info">
                                    @if($tiempoStr)
                                        <div class="mm-info-row">
                                            <x-heroicon-m-clock class="mm-info-icon" />
                                            <span>{{ $tiempoStr }}</span>
                                        </div>
                                    @endif
                                    @if($orden->total > 0)
                                        <div class="mm-info-row mm-info-total">
                                            <x-heroicon-m-banknotes class="mm-info-icon" />
                                            <span>S/ {{ number_format($orden->total, 2) }}</span>
                                        </div>
                                    @else
                                        <div class="mm-info-row mm-info-vacia">
                                            <x-heroicon-m-pencil-square class="mm-info-icon" />
                                            <span>Sin productos aún</span>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            {{-- Acciones --}}
                            <div class="mm-card-actions">
                                @if($ocupacion === 'libre')
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

                                @elseif($ocupacion === 'ocupada')
                                    @if($orden)
                                        <a
                                            href="{{ \App\Filament\Pdv\Resources\OrdenRest\OrdenRestResource::getUrl('edit', ['record' => $orden->id], tenant: filament()->getTenant()) }}"
                                            class="mm-btn mm-btn--ver"
                                        >
                                            <x-heroicon-m-clipboard-document-list class="mm-btn-icon" />
                                            Ver pedido
                                        </a>
                                    @endif
                                    <a
                                        href="{{ $orden ? \App\Filament\Pdv\Resources\OrdenRest\OrdenRestResource::getUrl('cobrar', ['record' => $orden->id], tenant: filament()->getTenant()) : '#' }}"
                                        class="mm-btn mm-btn--pagar"
                                    >
                                        <x-heroicon-m-banknotes class="mm-btn-icon" />
                                        Cobrar
                                    </a>

                                @elseif($ocupacion === 'pagando')
                                    @if($orden)
                                        <a
                                            href="{{ \App\Filament\Pdv\Resources\OrdenRest\OrdenRestResource::getUrl('edit', ['record' => $orden->id], tenant: filament()->getTenant()) }}"
                                            class="mm-btn mm-btn--ver"
                                        >
                                            <x-heroicon-m-clipboard-document-list class="mm-btn-icon" />
                                            Ver pedido
                                        </a>
                                    @endif
                                    <button
                                        wire:click="liberarMesa({{ $mesa->id }})"
                                        wire:confirm="¿Liberar la mesa sin cobrar? Esto cancelará el pedido si está vacío."
                                        class="mm-btn mm-btn--liberar"
                                    >
                                        <x-heroicon-m-x-circle class="mm-btn-icon" />
                                        Liberar
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif

    @endif

</div>

<style>
/* ── Root ────────────────────────────────────────────────────────────── */
.mm-root {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

/* ── Empty state ────────────────────────────────────────────────────── */
.mm-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .6rem;
    padding: 3rem 1rem;
    text-align: center;
    background: var(--mm-surface, #f8f8fa);
    border-radius: 1rem;
    border: 1.5px dashed var(--mm-border, #d1d5db);
}
.mm-empty-icon { width: 3rem; height: 3rem; color: var(--mm-muted, #9ca3af); }
.mm-empty-title { font-size: 1rem; font-weight: 600; margin: 0; }
.mm-empty-sub { font-size: .85rem; color: var(--mm-muted, #6b7280); margin: 0; }

/* ── Tabs ───────────────────────────────────────────────────────────── */
.mm-tabs {
    display: flex;
    gap: .5rem;
    flex-wrap: wrap;
}
.mm-tab {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .45rem .9rem;
    border-radius: .65rem;
    border: 1.5px solid var(--mm-border, #d1d5db);
    background: var(--mm-surface, #f3f4f6);
    font-size: .85rem;
    font-weight: 500;
    cursor: pointer;
    color: var(--mm-text, #374151);
    transition: all .15s;
}
.mm-tab:hover { border-color: #46449e; color: #46449e; }
.mm-tab--active {
    background: #46449e;
    border-color: #46449e;
    color: #fff;
}
.mm-tab-icon { width: .9rem; height: .9rem; }
.mm-tab-count {
    background: rgba(255,255,255,.25);
    border-radius: 999px;
    font-size: .7rem;
    font-weight: 700;
    padding: .1rem .4rem;
    line-height: 1.2;
}
.mm-tab--active .mm-tab-count { background: rgba(255,255,255,.3); }
.mm-tab:not(.mm-tab--active) .mm-tab-count { background: rgba(0,0,0,.08); }

/* ── Leyenda ────────────────────────────────────────────────────────── */
.mm-leyenda {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: .75rem;
    font-size: .78rem;
    color: var(--mm-muted, #6b7280);
}
.mm-leyenda-item { display: flex; align-items: center; gap: .35rem; font-weight: 500; }
.mm-dot { width: .65rem; height: .65rem; border-radius: 50%; flex-shrink: 0; }
.mm-dot--libre    { background: #22c55e; }
.mm-dot--ocupada  { background: #ef4444; }
.mm-dot--pagando  { background: #f59e0b; }
.mm-leyenda-sep { opacity: .4; }
.mm-leyenda-piso { font-weight: 600; color: var(--mm-text, #374151); }
.mm-leyenda-impresora { display: flex; align-items: center; gap: .25rem; }
.mm-leyenda-icon { width: .8rem; height: .8rem; }
.mm-leyenda-refresh { display: flex; align-items: center; gap: .3rem; }

/* ── Grid ───────────────────────────────────────────────────────────── */
.mm-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
    gap: 1rem;
}

/* ── Card ───────────────────────────────────────────────────────────── */
.mm-card {
    display: flex;
    flex-direction: column;
    gap: .6rem;
    padding: 1rem;
    border-radius: .875rem;
    border: 2px solid var(--mm-card-border, #e5e7eb);
    background: var(--mm-card-bg, #fff);
    transition: box-shadow .15s, transform .15s;
}
.mm-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.08); transform: translateY(-1px); }

.mm-card--libre   { --mm-card-border: #bbf7d0; --mm-card-bg: #f0fdf4; }
.mm-card--ocupada { --mm-card-border: #fca5a5; --mm-card-bg: #fff5f5; }
.mm-card--pagando { --mm-card-border: #fcd34d; --mm-card-bg: #fffbeb; }

/* ── Card header ────────────────────────────────────────────────────── */
.mm-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .4rem;
}
.mm-card-nombre {
    font-weight: 700;
    font-size: .95rem;
    color: var(--mm-text, #111827);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ── Badge ──────────────────────────────────────────────────────────── */
.mm-badge {
    font-size: .65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .03em;
    padding: .15rem .45rem;
    border-radius: 999px;
    flex-shrink: 0;
}
.mm-badge--libre   { background: #dcfce7; color: #15803d; }
.mm-badge--ocupada { background: #fee2e2; color: #b91c1c; }
.mm-badge--pagando { background: #fef3c7; color: #92400e; }

/* ── Capacidad ──────────────────────────────────────────────────────── */
.mm-card-cap {
    display: flex;
    align-items: center;
    gap: .35rem;
    font-size: .78rem;
    color: var(--mm-muted, #6b7280);
}
.mm-cap-icon { width: .85rem; height: .85rem; flex-shrink: 0; }

/* ── Info (ocupada/pagando) ─────────────────────────────────────────── */
.mm-card-info {
    display: flex;
    flex-direction: column;
    gap: .3rem;
    padding: .5rem .6rem;
    background: rgba(0,0,0,.04);
    border-radius: .5rem;
}
.mm-info-row {
    display: flex;
    align-items: center;
    gap: .35rem;
    font-size: .8rem;
    font-weight: 500;
}
.mm-info-icon { width: .8rem; height: .8rem; flex-shrink: 0; }
.mm-info-total { color: #15803d; font-weight: 700; font-size: .85rem; }
.mm-info-vacia { color: #6b7280; }

/* ── Acciones ───────────────────────────────────────────────────────── */
.mm-card-actions {
    display: flex;
    flex-direction: column;
    gap: .4rem;
    margin-top: auto;
}

.mm-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .35rem;
    padding: .45rem .75rem;
    border-radius: .55rem;
    font-size: .8rem;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: filter .15s, opacity .15s;
    text-decoration: none;
    width: 100%;
}
.mm-btn:disabled { opacity: .6; cursor: not-allowed; }
.mm-btn:not(:disabled):hover { filter: brightness(.92); }
.mm-btn-icon { width: .85rem; height: .85rem; flex-shrink: 0; }

.mm-btn--abrir   { background: #22c55e; color: #fff; }
.mm-btn--ver     { background: #46449e; color: #fff; }
.mm-btn--pagar   { background: #f59e0b; color: #fff; }
.mm-btn--liberar { background: transparent; color: #6b7280; border: 1px solid #d1d5db; }

/* ── Dark mode ──────────────────────────────────────────────────────── */
@media (prefers-color-scheme: dark) {
    .mm-empty   { --mm-surface: #1f2937; --mm-border: #374151; }
    .mm-tab     { --mm-surface: #1f2937; --mm-border: #374151; --mm-text: #d1d5db; }
    .mm-card    { --mm-card-border: #374151; --mm-card-bg: #1f2937; }
    .mm-card--libre   { --mm-card-border: #166534; --mm-card-bg: #052e16; }
    .mm-card--ocupada { --mm-card-border: #991b1b; --mm-card-bg: #2a0a0a; }
    .mm-card--pagando { --mm-card-border: #92400e; --mm-card-bg: #1c0d00; }
    .mm-card-nombre { --mm-text: #f9fafb; }
}
:root[data-theme="dark"] .mm-empty   { --mm-surface: #1f2937; --mm-border: #374151; }
:root[data-theme="dark"] .mm-tab     { --mm-surface: #1f2937; --mm-border: #374151; --mm-text: #d1d5db; }
:root[data-theme="dark"] .mm-card    { --mm-card-border: #374151; --mm-card-bg: #1f2937; }
:root[data-theme="dark"] .mm-card--libre   { --mm-card-border: #166534; --mm-card-bg: #052e16; }
:root[data-theme="dark"] .mm-card--ocupada { --mm-card-border: #991b1b; --mm-card-bg: #2a0a0a; }
:root[data-theme="dark"] .mm-card--pagando { --mm-card-border: #92400e; --mm-card-bg: #1c0d00; }
:root[data-theme="dark"] .mm-card-nombre { --mm-text: #f9fafb; }
</style>

</x-filament-panels::page>

<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/ventas-sesion.css') }}?v={{ filemtime(public_path('css/ventas-sesion.css')) }}">

@php
    $sesion  = $this->getSesionActiva();
    $resumen = $this->getResumen();
@endphp

<div class="vs-root">

    {{-- ══ TÍTULO ══ --}}
    <div class="vs-title">
        <div>
            <h1>Ventas del Turno</h1>
            @if($sesion)
                <p>{{ $sesion->caja?->nombre ?? 'Caja' }} — abierta el {{ $sesion->fecha_apertura->format('d/m/Y H:i') }}</p>
            @else
                <p>Sin sesión de caja activa</p>
            @endif
        </div>
        @if($sesion)
            <span class="vs-sesion-badge">
                <span class="vs-sesion-dot"></span>
                Sesión activa
            </span>
        @endif
    </div>

    @if(! $sesion)
        <div class="vs-empty-session">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
            </svg>
            <p>No tienes una sesión de caja abierta.</p>
            <span>Apertura una caja para comenzar a vender.</span>
        </div>
    @else

    {{-- ══ TARJETAS RESUMEN ══ --}}
    <div class="vs-cards">

        <div class="vs-card vs-card--green">
            <div class="vs-card__icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185Z"/>
                </svg>
            </div>
            <div class="vs-card__body">
                <span class="vs-card__label">Ventas</span>
                <span class="vs-card__value">{{ $resumen['count'] }}</span>
            </div>
        </div>

        <div class="vs-card vs-card--blue">
            <div class="vs-card__icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            </div>
            <div class="vs-card__body">
                <span class="vs-card__label">Total</span>
                <span class="vs-card__value">S/ {{ number_format($resumen['total'], 2) }}</span>
            </div>
        </div>

        @if(($resumen['descuentoTotal'] ?? 0) > 0)
        <div class="vs-card vs-card--red">
            <div class="vs-card__icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185ZM9.75 9.75c0 .414.336.75.75.75h.008a.75.75 0 0 0 .75-.75v-.008a.75.75 0 0 0-.75-.75H10.5a.75.75 0 0 0-.75.75v.008Zm4.5 4.5c0 .414.336.75.75.75h.008a.75.75 0 0 0 .75-.75v-.008a.75.75 0 0 0-.75-.75H15a.75.75 0 0 0-.75.75v.008Z"/>
                </svg>
            </div>
            <div class="vs-card__body">
                <span class="vs-card__label">Descuentos</span>
                <span class="vs-card__value">- S/ {{ number_format($resumen['descuentoTotal'], 2) }}</span>
            </div>
        </div>
        @endif

        @if(($resumen['cortesias'] ?? 0) > 0)
        <div class="vs-card vs-card--amber">
            <div class="vs-card__icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 1 0 9.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1 1 14.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
                </svg>
            </div>
            <div class="vs-card__body">
                <span class="vs-card__label">Cortesías</span>
                <span class="vs-card__value">{{ $resumen['cortesias'] }} ventas</span>
            </div>
        </div>
        @endif

        @if($resumen['anuladas'] > 0)
        <div class="vs-card vs-card--red">
            <div class="vs-card__icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            </div>
            <div class="vs-card__body">
                <span class="vs-card__label">Anuladas</span>
                <span class="vs-card__value">{{ $resumen['anuladas'] }}</span>
            </div>
        </div>
        @endif

        @if($resumen['despacho'] > 0)
        <div class="vs-card vs-card--amber">
            <div class="vs-card__icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                </svg>
            </div>
            <div class="vs-card__body">
                <span class="vs-card__label">Despacho pendiente</span>
                <span class="vs-card__value">{{ $resumen['despacho'] }}</span>
            </div>
        </div>
        @endif

    </div>

    {{-- ══ MÉTODOS DE PAGO ══ --}}
    @if(! empty($resumen['porMetodo']))
    <div class="vs-metodos">
        <span class="vs-metodos__titulo">Por método de pago</span>
        <div class="vs-metodos__lista">
            @foreach($resumen['porMetodo'] as $m)
                <div class="vs-metodo-item">
                    <span class="vs-metodo-item__nombre">{{ $m['nombre'] }}</span>
                    <span class="vs-metodo-item__monto">S/ {{ number_format($m['total'], 2) }}</span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ══ FILTROS ══ --}}
    <div class="vs-filters">
        <div class="vs-filter-busqueda">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
            </svg>
            <input
                type="text"
                wire:model.live.debounce.300ms="busqueda"
                placeholder="Buscar por cliente, documento o correlativo…"
                class="vs-filter-input"
            />
            @if($busqueda)
                <button wire:click="$set('busqueda', '')" class="vs-filter-clear">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            @endif
        </div>

        <select wire:model.live="filtroEstado" class="vs-filter-select">
            <option value="">Todos los estados</option>
            <option value="completada">Completadas</option>
            <option value="anulada">Anuladas</option>
        </select>

        @php $origenOpts = $this->getOrigenOptions(); @endphp
        @if(count($origenOpts) >= 2)
        <select wire:model.live="filtroOrigen" class="vs-filter-select">
            <option value="">Todos los orígenes</option>
            @foreach($origenOpts as $val => $label)
                <option value="{{ $val }}">{{ $label }}</option>
            @endforeach
        </select>
        @endif

        @if($busqueda || $filtroEstado || $filtroOrigen)
            <button wire:click="limpiarFiltros" class="vs-filter-reset">Limpiar</button>
        @endif
    </div>

    {{-- ══ TABLA (Filament) ══ --}}
    {{ $this->table }}

    @endif {{-- /sesion activa --}}

</div>{{-- /vs-root --}}


{{-- El modal de detalle ahora lo maneja Filament nativo (->modalContent) --}}

{{-- El modal de anular ahora lo maneja Filament nativo (sin blade custom) --}}

{{-- ══ IFRAME IMPRESIÓN EN PÁGINA ══ --}}
<div x-data="vsTicketPrint()"
     @pdv-imprimir-ticket.window="cargar($event.detail.url)">
    <iframe id="vs-ticket-frame"
        style="position:fixed;left:-9999px;top:-9999px;width:1px;height:1px;border:0;opacity:0;"
        @load="onLoad()"></iframe>
</div>

{{-- ══ MODAL CONVERTIR TICKET ══ --}}
@if($modalConvertir)
<div class="vs-overlay" wire:key="modal-convertir">
    <div class="vs-overlay__backdrop" wire:click="cerrarConvertir"></div>
    <div class="vs-modal vs-modal--convertir">

        {{-- Header --}}
        <div class="vs-modal__header">
            <div>
                <h3 class="vs-modal__titulo">Emitir comprobante electrónico</h3>
                <p class="vs-modal__subtitulo">Ticket <strong>{{ $convertirCodigo }}</strong> · S/ {{ number_format($convertirTotal, 2) }}</p>
            </div>
            <button class="vs-modal__cerrar" wire:click="cerrarConvertir">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Selector tipo --}}
        <div class="vs-conv-tipo-wrap">
            <button
                class="vs-conv-tipo-btn {{ $convertirTipo === 'boleta' ? 'vs-conv-tipo-btn--active' : '' }}"
                wire:click="$set('convertirTipo', 'boleta')"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185Z"/></svg>
                Boleta
            </button>
            <button
                class="vs-conv-tipo-btn {{ $convertirTipo === 'factura' ? 'vs-conv-tipo-btn--active' : '' }}"
                wire:click="$set('convertirTipo', 'factura')"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                Factura
            </button>
        </div>

        {{-- Búsqueda cliente --}}
        <div class="vs-conv-section">
            <label class="vs-conv-label">
                {{ $convertirTipo === 'factura' ? 'Cliente (RUC requerido)' : 'Cliente (opcional)' }}
            </label>
            <div class="vs-conv-search-wrap" x-data="{ open: @entangle('convertirMostrarSug') }">
                <div class="vs-conv-search-row">
                    <input
                        type="text"
                        class="vs-conv-input"
                        wire:model.live="convertirBusqueda"
                        placeholder="Buscar por nombre o documento…"
                        autocomplete="off"
                    />
                    @if($convertirClienteId)
                        <button class="vs-conv-clear-btn" wire:click="limpiarConvertirCliente" title="Quitar cliente">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                        </button>
                    @endif
                </div>

                @if($convertirClienteId)
                    <div class="vs-conv-cliente-sel">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                        <span><strong>{{ $convertirClienteNombre }}</strong> · {{ strtoupper($convertirClienteTipoDoc) }} {{ $convertirClienteNumDoc }}</span>
                    </div>
                @endif

                @if($convertirMostrarSug)
                    @php $sug = $this->getConvertirSugeridos(); @endphp
                    @if($sug->isNotEmpty())
                        <ul class="vs-conv-sugerencias">
                            @foreach($sug as $s)
                                <li wire:click="seleccionarConvertirCliente({{ $s->id }})" wire:key="sug-{{ $s->id }}">
                                    <span class="vs-conv-sug-nombre">{{ $s->nombre_completo }}</span>
                                    <span class="vs-conv-sug-doc">{{ strtoupper($s->tipo_documento->value) }} {{ $s->numero_documento }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="vs-conv-sug-empty">Sin resultados</p>
                    @endif
                @endif
            </div>
        </div>

        {{-- Preview IGV --}}
        <div class="vs-conv-igv-preview">
            <div class="vs-conv-igv-row">
                <span>Op. Gravadas</span>
                <span>S/ {{ number_format($convertirOpGravadas, 2) }}</span>
            </div>
            <div class="vs-conv-igv-row">
                <span>IGV ({{ $convertirIgvPct }}%)</span>
                <span>S/ {{ number_format($convertirIgv, 2) }}</span>
            </div>
            <div class="vs-conv-igv-row vs-conv-igv-row--total">
                <span>Total</span>
                <span>S/ {{ number_format($convertirTotal, 2) }}</span>
            </div>
        </div>

        {{-- Footer --}}
        <div class="vs-modal__footer">
            <button class="vs-modal__btn-cerrar" wire:click="cerrarConvertir">Cancelar</button>
            <button class="vs-btn-confirmar-convertir" wire:click="confirmarConvertir" wire:loading.attr="disabled">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                <span wire:loading.remove wire:target="confirmarConvertir">Emitir {{ $convertirTipo }}</span>
                <span wire:loading wire:target="confirmarConvertir">Procesando…</span>
            </button>
        </div>

    </div>
</div>
@endif

<script>
function vsTicketPrint() {
    return {
        pendingPrint: false,
        cargar(url) {
            this.pendingPrint = true;
            const iframe = document.getElementById('vs-ticket-frame');
            iframe.src = url;
        },
        onLoad() {
            if (!this.pendingPrint) return;
            this.pendingPrint = false;
            const iframe = document.getElementById('vs-ticket-frame');
            if (iframe && iframe.contentWindow) {
                iframe.contentWindow.print();
            }
        }
    };
}
</script>

</x-filament-panels::page>

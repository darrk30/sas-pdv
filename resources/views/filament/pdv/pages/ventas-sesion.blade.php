<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/ventas-sesion.css') }}?v={{ filemtime(public_path('css/ventas-sesion.css')) }}">

{{-- ══ TABLA (Filament) ══ --}}
{{ $this->table }}

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

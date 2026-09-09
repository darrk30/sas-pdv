<div>
@if($abierto && $ordenId && !empty($areas))
<div class="pdv-overlay" x-data="{ tab: 0 }">
    <div class="pdv-overlay__backdrop" wire:click="cerrar"></div>

    <div class="pdv-modal" style="max-width:520px;width:100%;max-height:88vh;">

        {{-- Header --}}
        <div class="pdv-modal__header">
            <div>
                <h3 class="pdv-modal__titulo">Comanda — {{ $mesa }}</h3>
                <p class="pdv-modal__subtitulo">
                    {{ count($areas) }} {{ count($areas) === 1 ? 'área de producción' : 'áreas de producción' }}
                </p>
            </div>
            <button class="pdv-modal__cerrar" wire:click="cerrar">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Tabs (solo si hay más de 1 área) --}}
        @if(count($areas) > 1)
        <div class="pdv-comanda-tabs">
            @foreach($areas as $i => $area)
            <button
                type="button"
                class="pdv-comanda-tab"
                :class="tab === {{ $i }} ? 'pdv-comanda-tab--activo' : ''"
                @click="tab = {{ $i }}"
            >
                {{ strtoupper($area['nombre']) }}
            </button>
            @endforeach
        </div>
        @endif

        {{-- Panels --}}
        <div class="pdv-modal__body" style="padding:1rem;gap:.75rem;">
            @foreach($areas as $i => $area)
            @php
                $params = http_build_query([
                    'area_nombre' => $area['nombre'],
                    'nuevos'      => json_encode($area['nuevos']),
                    'cancelados'  => json_encode($area['cancelados']),
                    'parcial'     => $parcial ? '1' : '0',
                    'mesa'        => $mesa,
                    'cajero'      => $cajero,
                ]);
                $src = route('pdv.ticket.comanda', $ordenId) . '?' . $params;
            @endphp
            <div x-show="tab === {{ $i }}" x-cloak>

                {{-- Indicador de contenido --}}
                @php
                    $tieneNuevos    = !empty($area['nuevos']);
                    $tieneCancelados = !empty($area['cancelados']);
                @endphp
                @if($tieneNuevos && $tieneCancelados)
                <div class="pdv-comanda-chips">
                    <span class="pdv-comanda-chip pdv-comanda-chip--nuevo">+{{ count($area['nuevos']) }} agregar</span>
                    <span class="pdv-comanda-chip pdv-comanda-chip--quitar">-{{ count($area['cancelados']) }} quitar</span>
                </div>
                @elseif($tieneNuevos)
                <div class="pdv-comanda-chips">
                    <span class="pdv-comanda-chip pdv-comanda-chip--nuevo">{{ count($area['nuevos']) }} producto(s) nuevo(s)</span>
                </div>
                @elseif($tieneCancelados)
                <div class="pdv-comanda-chips">
                    <span class="pdv-comanda-chip pdv-comanda-chip--quitar">{{ count($area['cancelados']) }} producto(s) cancelado(s)</span>
                </div>
                @endif

                {{-- Iframe de la comanda --}}
                <div class="pdv-comanda-iframe-wrap">
                    <iframe
                        id="comanda-frame-{{ $i }}"
                        src="{{ $src }}"
                        class="pdv-comanda-iframe"
                        scrolling="yes"
                    ></iframe>
                </div>

                {{-- Botón imprimir --}}
                <button
                    type="button"
                    class="pdv-btn-confirmar pdv-comanda-print-btn"
                    @click="document.getElementById('comanda-frame-{{ $i }}').contentWindow.print()"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1rem;height:1rem;flex-shrink:0;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/>
                    </svg>
                    Imprimir {{ strtoupper($area['nombre']) }}
                </button>
            </div>
            @endforeach
        </div>

        {{-- Cerrar --}}
        <div style="padding:.75rem 1.25rem;border-top:1px solid var(--pdv-border,#e2e8f0);">
            <button type="button" class="pdv-print-cerrar" wire:click="cerrar" style="width:100%;text-align:center;">
                Cerrar
            </button>
        </div>

    </div>
</div>

<style>
.pdv-comanda-tabs {
    display: flex;
    gap: 0;
    border-bottom: 1px solid var(--pdv-border, #e2e8f0);
    padding: 0 1.25rem;
    overflow-x: auto;
}
.pdv-comanda-tab {
    padding: .55rem 1rem;
    border: none;
    border-bottom: 2px solid transparent;
    background: transparent;
    color: var(--pdv-text-muted, #64748b);
    font-size: .78rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: color .12s, border-color .12s;
    letter-spacing: .03em;
}
.pdv-comanda-tab:hover { color: var(--pdv-text, #1e293b); }
.pdv-comanda-tab--activo {
    color: var(--pdv-primary, #6366f1);
    border-bottom-color: var(--pdv-primary, #6366f1);
}
.pdv-comanda-chips {
    display: flex;
    gap: .4rem;
    margin-bottom: .5rem;
}
.pdv-comanda-chip {
    display: inline-flex;
    align-items: center;
    padding: .15rem .55rem;
    border-radius: 99px;
    font-size: .72rem;
    font-weight: 700;
}
.pdv-comanda-chip--nuevo {
    background: #dcfce7;
    color: #166534;
}
.pdv-comanda-chip--quitar {
    background: #fee2e2;
    color: #991b1b;
}
.pdv-comanda-iframe-wrap {
    border: 1px solid var(--pdv-border, #e2e8f0);
    border-radius: .5rem;
    overflow: hidden;
    background: #fff;
}
.pdv-comanda-iframe {
    width: 100%;
    min-height: 280px;
    max-height: 340px;
    border: 0;
    display: block;
}
.pdv-comanda-print-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .4rem;
    margin-top: .6rem;
}
.dark .pdv-comanda-chip--nuevo { background: rgba(34,197,94,.15); color: #4ade80; }
.dark .pdv-comanda-chip--quitar { background: rgba(239,68,68,.15); color: #f87171; }
.dark .pdv-comanda-iframe-wrap { border-color: var(--pdv-border); }
</style>
@endif
</div>

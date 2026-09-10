<div>
@if($abierto && $ventaId)
    <div class="pdv-overlay pdv-overlay--print" wire:key="modal-venta-completada"
         x-data="{
             ventaId: {{ $ventaId }},
             autoImprimir: {{ $autoImprimir ? 'true' : 'false' }},
             iframeLoaded: false,
             imprimir() {
                 const iframe = document.getElementById('pdv-ticket-frame');
                 if (iframe && iframe.contentWindow) iframe.contentWindow.print();
             }
         }"
         x-init="iframeLoaded = false"
         @pdv-abrir-wsp.window="window.open($event.detail.url, '_blank')">

        <div class="pdv-overlay__backdrop" wire:click="cerrar"></div>
        <div class="pdv-modal pdv-modal--impresion">

            {{-- Header --}}
            <div class="pdv-modal__header">
                <div>
                    <h3 class="pdv-modal__titulo">¡Venta completada!</h3>
                    <p class="pdv-modal__subtitulo">{{ $ventaNumero }} &nbsp;·&nbsp; S/ {{ number_format($ventaTotal, 2) }}</p>
                </div>
                <button class="pdv-modal__cerrar" wire:click="cerrar">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Ícono éxito --}}
            <div class="pdv-print-success">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd"/>
                </svg>
                <span>Venta registrada</span>
            </div>

            {{-- Indicador impresión directa --}}
            @if(! $autoImprimir)
            <div style="display:flex;align-items:center;justify-content:center;gap:6px;font-size:12px;color:#16a34a;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:6px 12px;margin-bottom:4px;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:14px;height:14px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/>
                </svg>
                Comprobante enviado a la impresora
            </div>
            @endif

            {{-- iframe oculto para precargar el ticket --}}
            <iframe
                id="pdv-ticket-frame"
                src="{{ route('pdv.ticket.venta', $ventaId) }}"
                style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;border:0;"
                @load="iframeLoaded = true"
            ></iframe>

            {{-- Botón imprimir --}}
            <button class="pdv-print-btn" @click="imprimir()" :disabled="!iframeLoaded" :class="{'pdv-print-btn--loading': !iframeLoaded}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/>
                </svg>
                Imprimir comprobante
            </button>

            @if($wspShareUrl)
            <div class="pdv-print-divider">
                <span>Enviar al cliente</span>
            </div>

            {{-- WhatsApp --}}
            <div class="pdv-wsp-row">
                <div class="pdv-wsp-input-wrap">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="pdv-wsp-icon">
                        <path fill-rule="evenodd" d="M1.5 4.5a3 3 0 0 1 3-3h1.372c.86 0 1.61.586 1.819 1.42l1.105 4.423a1.875 1.875 0 0 1-.694 1.955l-1.293.97c-.135.101-.164.249-.126.352a11.285 11.285 0 0 0 6.697 6.697c.103.038.25.009.352-.126l.97-1.293a1.875 1.875 0 0 1 1.955-.694l4.423 1.105c.834.209 1.42.959 1.42 1.82V19.5a3 3 0 0 1-3 3h-2.25C8.552 22.5 1.5 15.448 1.5 6.75V4.5Z" clip-rule="evenodd"/>
                    </svg>
                    <input
                        type="tel"
                        class="pdv-wsp-input"
                        wire:model="wspTelefono"
                        placeholder="Número WhatsApp (ej: 999888777)"
                        maxlength="15"
                    />
                </div>
                <button class="pdv-wsp-btn" wire:click="enviarWhatsapp">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
                        <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                    </svg>
                    Enviar
                </button>
            </div>
            @endif

            <button class="pdv-print-cerrar" wire:click="cerrar">
                Cerrar
            </button>

        </div>
    </div>
@endif
</div>

<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/ventas-sesion.css') }}?v={{ filemtime(public_path('css/ventas-sesion.css')) }}">

@php $resumen = $this->getResumen(); @endphp

<div class="mb-3">
    {{ $this->form }}
    @if($this->hayFiltros())
        <div class="mt-3 flex justify-end">
            <x-filament::button wire:click="limpiarFiltros" color="gray" size="sm" icon="heroicon-m-x-mark" outlined>
                Limpiar filtros
            </x-filament::button>
        </div>
    @endif
</div>

{{ $this->table }}





{{-- ══ IFRAME IMPRESIÓN EN PÁGINA ══ --}}
<div x-data="vsTicketPrint()"
     @pdv-imprimir-ticket.window="cargar($event.detail.url)">
    <iframe id="vs-ticket-frame"
        style="position:fixed;left:-9999px;top:-9999px;width:1px;height:1px;border:0;opacity:0;"
        @load="onLoad()"></iframe>
</div>


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

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

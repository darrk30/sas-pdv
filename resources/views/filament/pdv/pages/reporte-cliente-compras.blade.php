<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/venta-detalle-modal.css') }}?v={{ filemtime(public_path('css/venta-detalle-modal.css')) }}">

{{ $this->table }}

@include('filament.pdv.partials.venta-detalle-modal')
</x-filament-panels::page>

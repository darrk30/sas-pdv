<x-filament-panels::page>

@php $pendientes = $this->getPendientesCount(); @endphp

@if($pendientes > 0)
<div class="flex items-center gap-2 px-4 py-2 mb-2 rounded-xl border
            bg-blue-50 border-blue-200 text-blue-700
            dark:bg-blue-950 dark:border-blue-800 dark:text-blue-300"
     style="width:fit-content">
    <span class="text-2xl font-bold leading-none">{{ $pendientes }}</span>
    <span class="text-xs leading-snug">
        pendiente{{ $pendientes !== 1 ? 's' : '' }}<br>
        <span class="opacity-70">para el próximo RC</span>
    </span>
</div>
@endif

{{ $this->table }}

</x-filament-panels::page>

<x-filament-panels::page>

<div>
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

</x-filament-panels::page>

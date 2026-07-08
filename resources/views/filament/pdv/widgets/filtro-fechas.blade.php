<x-filament-widgets::widget>
    <x-filament::section>
        <div style="display:flex; flex-wrap:wrap; align-items:center; gap:.5rem;">

            <span style="font-size:.8125rem; font-weight:500; color:#6b7280; margin-right:.25rem;">Período:</span>

            @foreach (['hoy' => 'Hoy', 'semana' => 'Esta semana', 'mes' => 'Este mes', 'personalizado' => 'Personalizado'] as $valor => $etiqueta)
            <button
                type="button"
                wire:click="setFiltro('{{ $valor }}')"
                class="wi-btn {{ $filtro === $valor ? 'wi-activo' : '' }}">{{ $etiqueta }}</button>
            @endforeach

            @if ($filtro === 'personalizado')
            <div style="display:flex; flex-wrap:wrap; align-items:center; gap:.5rem; margin-left:.25rem;">
                <input type="date" wire:model.live="desde" class="wi-date-input" />
                <span style="color:#9ca3af;">→</span>
                <input type="date" wire:model.live="hasta" class="wi-date-input" />
            </div>
            @endif

            {{-- Separador y estado de caché --}}
            <div style="display:flex; align-items:center; gap:.625rem; margin-left:auto; flex-wrap:wrap;">
                @if ($cacheInfo)
                <span style="font-size:.75rem; color:#9ca3af; display:flex; align-items:center; gap:.25rem;">
                    <svg style="width:.875rem; height:.875rem; flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    {{ $cacheInfo }}
                </span>
                @endif

                <button
                    type="button"
                    wire:click="refrescar"
                    wire:loading.attr="disabled"
                    wire:target="refrescar"
                    title="Forzar actualización de datos"
                    style="display:flex; align-items:center; gap:.375rem; border-radius:.5rem; border:1px solid #e5e7eb; background:transparent; padding:.3125rem .625rem; font-size:.75rem; font-weight:500; color:#6b7280; cursor:pointer; transition:background .15s, color .15s;"
                    onmouseover="this.style.background='#f3f4f6'; this.style.color='#111827';"
                    onmouseout="this.style.background='transparent'; this.style.color='#6b7280';">
                    <svg wire:loading.class="wi-spin" wire:target="refrescar" style="width:.875rem; height:.875rem;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    <span wire:loading.remove wire:target="refrescar">Actualizar</span>
                    <span wire:loading wire:target="refrescar">Cargando…</span>
                </button>
            </div>

        </div>
    </x-filament::section>
</x-filament-widgets::widget>

<style>
    @keyframes wi-spin {
        to {
            transform: rotate(360deg);
        }
    }

    .wi-spin {
        animation: wi-spin .8s linear infinite;
    }
</style>
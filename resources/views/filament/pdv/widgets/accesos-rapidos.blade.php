<x-filament-widgets::widget>
    <x-filament::section heading="Acceso rápido">
        <div style="display:flex; flex-wrap:wrap; gap:.75rem;">
            @foreach ($accesos as $acceso)
                <a href="{{ $acceso['url'] }}" class="wi-card wi-card-{{ $acceso['color'] }}">
                    <div class="wi-icon wi-icon-{{ $acceso['color'] }}" style="width:2.75rem; height:2.75rem; border-radius:9999px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <x-filament::icon :icon="$acceso['icon']" style="width:1.375rem; height:1.375rem;" />
                    </div>
                    <span class="wi-card-label">{{ $acceso['label'] }}</span>
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

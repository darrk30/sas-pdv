@if(!empty($stats))
<x-filament-widgets::widget>
<style>
.vsr-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: .375rem .75rem;
    padding: .625rem 1.25rem;
}
.vsr-item {
    display: flex;
    align-items: center;
    gap: .4rem;
}
.vsr-label {
    font-size: .75rem;
    font-weight: 500;
    color: var(--gray-500, #6b7280);
}
.dark .vsr-label { color: var(--gray-400, #9ca3af); }
.vsr-desde {
    margin-left: auto;
    font-size: .7rem;
    color: var(--gray-400, #9ca3af);
    white-space: nowrap;
}
@media (max-width: 640px) { .vsr-desde { display: none; } }
</style>
    <div class="vsr-row">
        @foreach($stats as $s)
            <div class="vsr-item">
                <span class="vsr-label">{{ $s['label'] }}</span>
                <x-filament::badge :color="$s['color']" size="sm">{{ $s['value'] }}</x-filament::badge>
            </div>
        @endforeach
        @if($desde)
            <span class="vsr-desde">{{ $desde }}</span>
        @endif
    </div>
</x-filament-widgets::widget>
@endif

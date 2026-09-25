@if(!empty($metodos))
<x-filament-widgets::widget>
<style>
.vsm-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: .5rem;
}
.vsm-label {
    font-size: .7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--gray-400, #9ca3af);
    margin-right: .25rem;
}
.vsm-card {
    display: flex;
    flex-direction: column;
    gap: .1rem;
    padding: .375rem .75rem;
    border-radius: .5rem;
    background: var(--gray-50, #f9fafb);
    border: 1px solid var(--gray-200, #e5e7eb);
    min-width: 80px;
}
.dark .vsm-card {
    background: rgba(255,255,255,.04);
    border-color: rgba(255,255,255,.08);
}
.vsm-card--total {
    background: rgba(99,102,241,.07);
    border-color: rgba(99,102,241,.25);
}
.dark .vsm-card--total {
    background: rgba(99,102,241,.15);
    border-color: rgba(99,102,241,.3);
}
.vsm-card__name {
    font-size: .6875rem;
    color: var(--gray-500, #6b7280);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.dark .vsm-card__name { color: var(--gray-400, #9ca3af); }
.vsm-card--total .vsm-card__name {
    color: rgba(99,102,241,.8);
}
.vsm-card__amount {
    font-size: .8125rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    color: var(--gray-900, #111827);
}
.dark .vsm-card__amount { color: var(--gray-100, #f3f4f6); }
.vsm-card--total .vsm-card__amount {
    color: rgb(79,70,229);
}
.dark .vsm-card--total .vsm-card__amount {
    color: rgb(165,180,252);
}
</style>
    <div class="vsm-row">
        <span class="vsm-label">Métodos</span>
        @foreach($metodos as $m)
            <div class="vsm-card">
                <span class="vsm-card__name">{{ $m['nombre'] }}</span>
                <span class="vsm-card__amount">S/ {{ number_format($m['total'], 2) }}</span>
            </div>
        @endforeach
        @if(count($metodos) > 1)
            <div class="vsm-card vsm-card--total">
                <span class="vsm-card__name">Total</span>
                <span class="vsm-card__amount">S/ {{ number_format($total, 2) }}</span>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
@endif

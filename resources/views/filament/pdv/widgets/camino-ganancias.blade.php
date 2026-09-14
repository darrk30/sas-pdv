@php
    $d        = $this->getData();
    $pct      = $d['pct'];
    $ganando  = $d['ganando'];
    $mes      = ucfirst($d['mes']);
    $dif      = $d['diferencia'];

    $ganColor = $dif > 0 ? '#16a34a' : ($dif < 0 ? '#dc2626' : 'var(--gray-500,#6b7280)');
    $ganLabel = $dif > 0
        ? '+S/ ' . number_format($dif, 2)
        : ($dif < 0 ? '−S/ ' . number_format(abs($dif), 2) : 'S/ 0.00');
@endphp

<x-filament-widgets::widget>
<style>
.cg-header-right {
    text-align: right;
    line-height: 1.2;
    flex-shrink: 0;
}
.cg-header-right-label {
    display: block;
    font-size: .68rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--gray-400, #9ca3af);
}
.cg-header-right-value {
    font-size: 1.65rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    color: {{ $ganColor }};
}
.cg-bar-labels {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    flex-wrap: wrap;
    gap: .25rem .5rem;
    margin-bottom: .4rem;
}
.cg-bar-label {
    font-size: .78rem;
    font-weight: 600;
    color: var(--gray-500, #6b7280);
}
.cg-bar-label span { font-weight: 400; }
.cg-bar-status {
    margin-top: .4rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: .25rem;
}
.cg-breakdown {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
}
@media (max-width: 479px) {
    .cg-breakdown { grid-template-columns: 1fr; }
    .cg-header-right-value { font-size: 1.3rem; }
}
.cg-breakdown-title {
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--gray-400, #9ca3af);
    margin: 0 0 .6rem;
}
.cg-breakdown-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: .5rem;
    font-size: .82rem;
}
.cg-breakdown-row--total {
    font-size: .85rem;
    font-weight: 700;
}
.cg-breakdown-divider {
    height: 1px;
    background: var(--gray-200, #e5e7eb);
    margin: .25rem 0;
}
.cg-note {
    margin-top: 1rem;
    padding: .65rem .85rem;
    border-radius: .5rem;
    background: var(--gray-50, #f9fafb);
    border: 1px solid var(--gray-200, #e5e7eb);
    font-size: .75rem;
    color: var(--gray-500, #6b7280);
    line-height: 1.6;
    margin-bottom: 0;
}
.cg-note strong { color: var(--gray-700, #374151); }
</style>
<x-filament::section heading="Camino a tus ganancias" :description="$mes">

    <x-slot name="afterHeader">
        <div class="cg-header-right">
            <span class="cg-header-right-label">Tus Ganancias</span>
            <span class="cg-header-right-value">{{ $ganLabel }}</span>
        </div>
    </x-slot>

    {{-- Barra de progreso --}}
    <div style="margin-bottom:1.25rem;">
        <div class="cg-bar-labels">
            <span class="cg-bar-label">
                S/ {{ number_format($d['totalNeto'], 2) }}
                <span>vendido neto</span>
            </span>
            <span class="cg-bar-label">Meta S/ {{ number_format($d['meta'], 2) }}</span>
        </div>

        <div style="position:relative;height:.65rem;border-radius:9999px;background:var(--gray-100,#f3f4f6);overflow:hidden;">
            <div style="height:100%;border-radius:9999px;width:{{ $pct }}%;background:{{ $ganando ? '#16a34a' : '#f59e0b' }};transition:width .4s ease;"></div>
        </div>

        <div class="cg-bar-status">
            <span style="font-size:.72rem;font-weight:700;color:{{ $ganando ? '#16a34a' : '#d97706' }};">
                {{ $pct }}% de la meta
            </span>
            @if($ganando)
                <span style="font-size:.72rem;font-weight:600;color:#16a34a;">
                    ✓ +S/ {{ number_format($dif, 2) }} sobre la meta
                </span>
            @else
                <span style="font-size:.72rem;font-weight:600;color:#d97706;">
                    Faltan S/ {{ number_format(abs($dif), 2) }} para cubrir gastos
                </span>
            @endif
        </div>
    </div>

    <div style="height:1px;background:var(--gray-200,#e5e7eb);margin:.75rem 0;"></div>

    {{-- Desglose --}}
    <div class="cg-breakdown">

        <div>
            <p class="cg-breakdown-title">De dónde sale tu avance</p>
            <div style="display:flex;flex-direction:column;gap:.3rem;">
                <div class="cg-breakdown-row">
                    <span style="color:var(--gray-700,#374151);">Cobrado</span>
                    <span style="font-weight:600;">S/ {{ number_format($d['totalBruto'], 2) }}</span>
                </div>
                <div class="cg-breakdown-row">
                    <span style="color:var(--gray-500,#6b7280);">− IGV (va a SUNAT)</span>
                    <span style="color:#dc2626;">−S/ {{ number_format($d['totalIgv'], 2) }}</span>
                </div>
                <div class="cg-breakdown-divider"></div>
                <div class="cg-breakdown-row cg-breakdown-row--total">
                    <span>= Avance real</span>
                    <span style="color:{{ $ganando ? '#16a34a' : '#d97706' }};">S/ {{ number_format($d['totalNeto'], 2) }}</span>
                </div>
            </div>
        </div>

        <div>
            <p class="cg-breakdown-title">De dónde sale la meta</p>
            <div style="display:flex;flex-direction:column;gap:.3rem;">
                <div class="cg-breakdown-row">
                    <span style="color:var(--gray-700,#374151);">Gastos fijos (mes)</span>
                    <span style="font-weight:600;">S/ {{ number_format($d['totalFijosMensual'], 2) }}</span>
                </div>
                <div class="cg-breakdown-row">
                    <span style="color:var(--gray-500,#6b7280);">+ Gastos variables</span>
                    <span>S/ {{ number_format($d['gastosVariables'], 2) }}</span>
                </div>
                <div class="cg-breakdown-divider"></div>
                <div class="cg-breakdown-row cg-breakdown-row--total">
                    <span>= Meta del período</span>
                    <span>S/ {{ number_format($d['meta'], 2) }}</span>
                </div>
            </div>
        </div>

    </div>

    <p class="cg-note">
        <strong>¿Por qué restamos el IGV?</strong>
        Cuando cobras S/ {{ number_format($d['totalBruto'], 2) }}, el IGV (S/ {{ number_format($d['totalIgv'], 2) }}) lo retienes para pagarlo a SUNAT — ese dinero no es ganancia del negocio.
        Tu avance real es solo lo que queda después: S/ {{ number_format($d['totalNeto'], 2) }}.
    </p>

</x-filament::section>
</x-filament-widgets::widget>

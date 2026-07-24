<x-filament-panels::page>
<style>
.rb-root { display: flex; flex-direction: column; gap: 1.25rem; }

.rb-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}
.rb-header h1 {
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--color-gray-900, #111827);
}
.rb-header p {
    font-size: .85rem;
    color: var(--color-gray-500, #6b7280);
    margin-top: .15rem;
}

.rb-pending {
    display: flex;
    align-items: center;
    gap: .6rem;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: .65rem;
    padding: .55rem 1rem;
}
.rb-pending__count {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1d4ed8;
    line-height: 1;
}
.rb-pending__text { font-size: .8rem; color: #1e40af; line-height: 1.3; }

@media (prefers-color-scheme: dark) {
    .rb-header h1  { color: #f9fafb; }
    .rb-header p   { color: #9ca3af; }
    .rb-pending    { background: #1e3a5f; border-color: #1d4ed8; }
    .rb-pending__count { color: #93c5fd; }
    .rb-pending__text  { color: #bfdbfe; }
}
:root[data-theme="dark"]  .rb-header h1         { color: #f9fafb; }
:root[data-theme="dark"]  .rb-header p          { color: #9ca3af; }
:root[data-theme="dark"]  .rb-pending           { background: #1e3a5f; border-color: #1d4ed8; }
:root[data-theme="dark"]  .rb-pending__count    { color: #93c5fd; }
:root[data-theme="dark"]  .rb-pending__text     { color: #bfdbfe; }
:root[data-theme="light"] .rb-header h1         { color: #111827; }
:root[data-theme="light"] .rb-header p          { color: #6b7280; }
:root[data-theme="light"] .rb-pending           { background: #eff6ff; border-color: #bfdbfe; }
:root[data-theme="light"] .rb-pending__count    { color: #1d4ed8; }
:root[data-theme="light"] .rb-pending__text     { color: #1e40af; }
</style>

@php
    $pendientes = $this->getPendientesCount();
@endphp

<div class="rb-root">

    {{-- ══ ENCABEZADO ══ --}}
    <div class="rb-header">
        <div>
            <h1>Resumenes SUNAT</h1>
            <p>RC Diario (boletas) · RA Baja (anulaciones de facturas)</p>
        </div>

        @if($pendientes > 0)
        <div class="rb-pending">
            <span class="rb-pending__count">{{ $pendientes }}</span>
            <span class="rb-pending__text">
                pendiente{{ $pendientes !== 1 ? 's' : '' }}<br>
                <span style="font-size:.72rem;opacity:.8">para el próximo RC</span>
            </span>
        </div>
        @endif
    </div>

    {{-- ══ TABLA ══ --}}
    {{ $this->table }}

</div>

</x-filament-panels::page>

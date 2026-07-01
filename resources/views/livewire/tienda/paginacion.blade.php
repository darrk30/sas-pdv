@if ($paginator->hasPages())
@php
    $curr = $paginator->currentPage();
    $last = $paginator->lastPage();

    // Páginas a mostrar: primera, última y ±2 del actual
    $shown = array_values(array_filter(
        range(1, $last),
        fn($p) => $p === 1 || $p === $last || abs($p - $curr) <= 2
    ));

    // Construir secuencia insertando null donde hay salto
    $seq  = [];
    $prev = null;
    foreach ($shown as $p) {
        if ($prev !== null && $p - $prev > 1) $seq[] = null;
        $seq[] = $p;
        $prev  = $p;
    }
@endphp
<nav class="pag" aria-label="Paginación">

    {{-- Flecha anterior --}}
    @if ($paginator->onFirstPage())
        <span class="pag__flecha pag__flecha--off" aria-disabled="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="13" height="13"><path d="M15 18l-6-6 6-6"/></svg>
        </span>
    @else
        <button wire:click="previousPage" wire:loading.attr="disabled" class="pag__flecha" aria-label="Anterior">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="13" height="13"><path d="M15 18l-6-6 6-6"/></svg>
        </button>
    @endif

    {{-- Números con elipsis --}}
    @foreach ($seq as $p)
        @if ($p === null)
            <span class="pag__puntos" aria-hidden="true">…</span>
        @elseif ($p === $curr)
            <span class="pag__num pag__num--activo" aria-current="page">{{ $p }}</span>
        @else
            @php
                $esExtremo = $p === 1 || $p === $last;
                $distancia = abs($p - $curr);
            @endphp
            <button
                wire:click="gotoPage({{ $p }})"
                wire:loading.attr="disabled"
                class="pag__num{{ $distancia === 2 && !$esExtremo ? ' pag__num--ext' : '' }}"
                aria-label="Página {{ $p }}"
            >{{ $p }}</button>
        @endif
    @endforeach

    {{-- Flecha siguiente --}}
    @if ($paginator->hasMorePages())
        <button wire:click="nextPage" wire:loading.attr="disabled" class="pag__flecha" aria-label="Siguiente">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="13" height="13"><path d="M9 18l6-6-6-6"/></svg>
        </button>
    @else
        <span class="pag__flecha pag__flecha--off" aria-disabled="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="13" height="13"><path d="M9 18l6-6-6-6"/></svg>
        </span>
    @endif

</nav>
@endif

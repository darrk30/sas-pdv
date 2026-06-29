@if ($paginator->hasPages())
    <div class="paginacion">
        <button
            wire:click="previousPage"
            wire:loading.attr="disabled"
            class="paginacion__btn"
            @disabled($paginator->onFirstPage())
        >
            ← Anterior
        </button>

        <span class="paginacion__info">
            Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}
        </span>

        <button
            wire:click="nextPage"
            wire:loading.attr="disabled"
            class="paginacion__btn"
            @disabled(! $paginator->hasMorePages())
        >
            Siguiente →
        </button>
    </div>
@endif

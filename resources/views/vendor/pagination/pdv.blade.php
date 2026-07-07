@if ($paginator->hasPages())
<nav class="pdv-pag" role="navigation" aria-label="Paginación">
    <span class="pdv-pag__info">
        Mostrando <strong>{{ $paginator->firstItem() }}</strong>–<strong>{{ $paginator->lastItem() }}</strong>
        de <strong>{{ $paginator->total() }}</strong>
    </span>

    <div class="pdv-pag__links">
        {{-- Anterior --}}
        @if ($paginator->onFirstPage())
        <span class="pdv-pag__btn pdv-pag__btn--disabled" aria-disabled="true">
            <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16">
                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
            </svg>
        </span>
        @else
        <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" class="pdv-pag__btn">
            <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16">
                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
            </svg>
        </button>
        @endif

        {{-- Números de página --}}
        @foreach ($elements as $element)
        @if (is_string($element))
        <span class="pdv-pag__btn pdv-pag__btn--dots">{{ $element }}</span>
        @endif

        @if (is_array($element))
        @foreach ($element as $page => $url)
        @if ($page == $paginator->currentPage())
        <span class="pdv-pag__btn pdv-pag__btn--active" aria-current="page">{{ $page }}</span>
        @else
        <button type="button" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" class="pdv-pag__btn">{{ $page }}</button>
        @endif
        @endforeach
        @endif
        @endforeach

        {{-- Siguiente --}}
        @if ($paginator->hasMorePages())
        <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" class="pdv-pag__btn">
            <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16">
                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
            </svg>
        </button>
        @else
        <span class="pdv-pag__btn pdv-pag__btn--disabled" aria-disabled="true">
            <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16">
                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
            </svg>
        </span>
        @endif
    </div>
</nav>
@endif

<style>
    .pdv-pag {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: .5rem;
        font-size: .8125rem;
    }

    .pdv-pag__info {
        color: rgb(100 116 139);
        line-height: 1.5;
    }

    .dark .pdv-pag__info {
        color: rgb(148 163 184);
    }

    .pdv-pag__links {
        display: flex;
        align-items: center;
        gap: .25rem;
    }

    .pdv-pag__btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2rem;
        height: 2rem;
        padding: 0 .5rem;
        border-radius: .375rem;
        font-size: .8125rem;
        font-weight: 500;
        line-height: 1;
        text-decoration: none;
        border: 1px solid rgb(226 232 240);
        background: #fff;
        color: rgb(51 65 85);
        transition: background .12s, color .12s, border-color .12s;
        cursor: pointer;
        font-family: inherit;
    }

    .pdv-pag__btn:hover:not(.pdv-pag__btn--disabled):not(.pdv-pag__btn--active):not(.pdv-pag__btn--dots) {
        background: rgb(241 245 249);
        border-color: rgb(203 213 225);
    }

    .pdv-pag__btn--active {
        background: rgb(99 102 241);
        border-color: rgb(99 102 241);
        color: #fff;
        cursor: default;
    }

    .pdv-pag__btn--disabled {
        color: rgb(203 213 225);
        border-color: rgb(226 232 240);
        background: #fff;
        cursor: not-allowed;
    }

    .pdv-pag__btn--dots {
        border-color: transparent;
        background: none;
        cursor: default;
        color: rgb(148 163 184);
    }

    .dark .pdv-pag__btn {
        background: rgb(30 41 59);
        border-color: rgb(51 65 85);
        color: rgb(203 213 225);
    }

    .dark .pdv-pag__btn:hover:not(.pdv-pag__btn--disabled):not(.pdv-pag__btn--active):not(.pdv-pag__btn--dots) {
        background: rgb(51 65 85);
        border-color: rgb(71 85 105);
    }

    .dark .pdv-pag__btn--active {
        background: rgb(99 102 241);
        border-color: rgb(99 102 241);
        color: #fff;
    }

    .dark .pdv-pag__btn--disabled {
        background: rgb(30 41 59);
        border-color: rgb(51 65 85);
        color: rgb(71 85 105);
    }
</style>

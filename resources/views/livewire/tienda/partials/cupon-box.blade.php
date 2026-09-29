{{-- Partial: caja de cupón de descuento --}}
@php $cuponAplicado = isset($cuponId) ? $cuponId : ($this->cuponId ?? null); @endphp

<div class="cr-cupon">
    @if ($cuponAplicado)
        {{-- Cupón aplicado --}}
        <div class="cr-cupon-aplicado">
            <div class="cr-cupon-aplicado__info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     width="14" height="14" style="color:#16a34a;flex-shrink:0">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="cr-cupon-aplicado__codigo">{{ $this->cuponCodigo }}</span>
                <span class="cr-cupon-aplicado__desc">aplicado</span>
            </div>
            <button type="button" class="cr-cupon-aplicado__quitar"
                    wire:click="quitarCupon"
                    wire:loading.attr="disabled"
                    title="Quitar cupón">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2.5" width="12" height="12">
                    <path d="M18 6 6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>
    @else
        {{-- Input de código --}}
        <label class="cr-cupon-label">¿Tienes un código de descuento?</label>
        <div class="cr-cupon-row">
            <input type="text"
                   class="cr-cupon-input"
                   placeholder="Ej: VERANO25"
                   maxlength="50"
                   wire:model="chkCupon"
                   wire:keydown.enter="aplicarCupon"
                   autocomplete="off"
                   autocapitalize="characters"
                   style="text-transform:uppercase">
            <button type="button"
                    class="cr-cupon-btn"
                    wire:click="aplicarCupon"
                    wire:loading.attr="disabled"
                    wire:target="aplicarCupon">
                <span wire:loading.remove wire:target="aplicarCupon">Aplicar</span>
                <span wire:loading wire:target="aplicarCupon">...</span>
            </button>
        </div>
        @if ($this->cuponError)
            <p class="cr-cupon-error">{{ $this->cuponError }}</p>
        @endif
    @endif
</div>

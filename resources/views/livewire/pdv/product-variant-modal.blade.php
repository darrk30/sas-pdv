<div style="display:contents">
@if($modalAbierto)
<div class="pdv-overlay" x-data="{
        cantidad: {{ $modalCantidad }},
        esCortesia: {{ $modalCortesia ? 'true' : 'false' }},
        puedeCortesia: {{ $productoEsCortesia ? 'true' : 'false' }},
        esDecimal: {{ $productoEsDecimal ? 'true' : 'false' }},
    }">
    <div class="pdv-overlay__backdrop" wire:click="cerrarModal"></div>
    <div class="pdv-modal">

        {{-- Header --}}
        <div class="pdv-modal__header">
            <h3 class="pdv-modal__titulo">{{ $productoModalNombre }}</h3>
            <button class="pdv-modal__cerrar" wire:click="cerrarModal">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Body scrollable --}}
        <div class="pdv-modal__body">

            {{-- Precio + Cortesía --}}
            @php
                $stockVarianteModal = null;
                if ($productoControlStock && count($atributosModal) > 0 && count($seleccionados) >= count($atributosModal)) {
                    $selectedPavIds = array_values($seleccionados);
                    foreach ($variantesInfo as $vInfo) {
                        if (count($vInfo['pav_ids']) === count($selectedPavIds)
                            && empty(array_diff($selectedPavIds, $vInfo['pav_ids']))) {
                            $stockVarianteModal = (float) $vInfo['stock'];
                            break;
                        }
                    }
                }
                $stockNivelModal = $stockVarianteModal === null ? null
                    : ($stockVarianteModal <= 0
                        ? ($productoVentaSinStock ? 'sin-stock' : 'agotado')
                        : ($stockVarianteModal <= 5 ? 'bajo' : 'ok'));
            @endphp
            <div class="pdv-modal__precio-row">
                <div>
                    <p class="pdv-modal__precio-label">Precio</p>
                    <p class="pdv-modal__precio-total" x-text="'S/ ' + ({{ $precioBase }} + {{ $precioAdicionalTotal }}).toFixed(2)"
                        :class="esCortesia ? 'pdv-modal__precio-gratis' : ''"></p>
                    @if($precioBase < $precioBaseOriginal)
                        <p class="pdv-modal__precio-detalle" style="text-decoration:line-through;">S/ {{ number_format($precioBaseOriginal + $precioAdicionalTotal, 2) }}</p>
                    @endif
                </div>
                <div class="pdv-modal__precio-right">
                    @if($stockVarianteModal !== null)
                        <span class="pdv-modal__stock-badge pdv-modal__stock-badge--{{ $stockNivelModal }}">
                            @if($stockNivelModal === 'agotado')
                                Agotado
                            @elseif($stockNivelModal === 'sin-stock')
                                Sin stock
                            @else
                                Stock {{ $productoEsDecimal ? number_format($stockVarianteModal, 2) : (int) $stockVarianteModal }} {{ $productoUnidadSimbolo ?: 'unid.' }}
                            @endif
                        </span>
                    @endif
                    <template x-if="puedeCortesia">
                        <button
                            type="button"
                            class="pdv-modal__cortesia-btn"
                            :class="esCortesia ? 'pdv-modal__cortesia-btn--on' : ''"
                            @click="esCortesia = !esCortesia"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:.8rem;height:.8rem;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z"/>
                            </svg>
                            Cortesía
                        </button>
                    </template>
                </div>
            </div>

            {{-- Atributos --}}
            @foreach($atributosModal as $atributo)
            <div>
                <p class="pdv-atributo__label">
                    {{ $atributo['nombre'] }}
                    <span class="pdv-atributo__requerido">requerido</span>
                </p>
                <div class="pdv-atributo__opciones">
                    @foreach($atributo['valores'] as $valor)
                        @php
                            $seleccionado  = isset($seleccionados[$atributo['id']]) && (int)$seleccionados[$atributo['id']] === (int)$valor['id'];
                            $deshabilitado = in_array((int)$valor['id'], $valoresDeshabilitados);
                        @endphp
                        <button
                            type="button"
                            class="pdv-valor-btn {{ $seleccionado ? 'pdv-valor-btn--activo' : '' }}"
                            wire:click="seleccionarValor({{ $atributo['id'] }}, {{ $valor['id'] }})"
                            @if($deshabilitado) disabled style="opacity:.35;cursor:not-allowed;" @endif
                        >
                            {{ $valor['nombre'] }}
                            @if($valor['precio_adicional'] > 0)
                                <span class="pdv-valor-btn__extra">+{{ number_format($valor['precio_adicional'], 2) }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
            @endforeach

        </div>{{-- /body --}}

        {{-- Footer: cantidad + confirmar --}}
        <div class="pdv-modal__footer">
            <div class="pdv-modal__qty-row">
                <span class="pdv-modal__qty-label">Cantidad</span>
                <div class="pdv-modal__qty">
                    <button type="button" class="pdv-qty__btn pdv-qty__btn--menos"
                        @click="cantidad = esDecimal ? Math.max(0.1, +(cantidad - 0.1).toFixed(3)) : Math.max(1, cantidad - 1)">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
                    </button>
                    <input
                        type="number"
                        class="pdv-modal__qty-input"
                        x-model.number="cantidad"
                        :min="esDecimal ? 0.001 : 1"
                        :step="esDecimal ? 0.1 : 1"
                    />
                    <button type="button" class="pdv-qty__btn pdv-qty__btn--mas"
                        @click="cantidad = esDecimal ? +(cantidad + 0.1).toFixed(3) : cantidad + 1">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    </button>
                </div>
            </div>

            <button
                type="button"
                class="pdv-btn-confirmar"
                style="display:flex;align-items:center;justify-content:center;gap:.4rem;"
                @click="$wire.confirmarModalConParams(cantidad, esCortesia)"
                @if(count($seleccionados) < count($atributosModal)) disabled @endif
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:1rem;height:1rem;flex-shrink:0;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
                </svg>
                Agregar al pedido
            </button>
        </div>

    </div>
</div>
@endif
</div>

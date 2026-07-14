<div
    x-data="modalVariante()"
    @abrir-modal-variante.window="abrir($event.detail)"
    @keydown.escape.window="cerrar()"
    x-show="abierto"
    style="display:none"
    class="modal-var"
>
    {{-- Overlay --}}
    <div class="modal-var__overlay" @click="cerrar()"></div>

    {{-- Diálogo --}}
    <div class="modal-var__dialog" @click.stop>

        {{-- Header --}}
        <div class="modal-var__header">
            <h2 class="modal-var__titulo" x-text="producto?.nombre ?? ''"></h2>
            <button type="button" class="modal-var__cerrar" @click="cerrar()" title="Cerrar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14">
                    <path d="M18 6 6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Cuerpo --}}
        <div class="modal-var__cuerpo">

            {{-- Imagen del producto / variante --}}
            <div class="modal-var__imagen">
                <img x-ref="imgPreview"
                     :src="imgPreview"
                     x-show="imgPreview"
                     alt=""
                     class="modal-var__img">
                <div class="modal-var__sin-img" x-show="!imgPreview">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" width="36" height="36" style="color:#d1d5db">
                        <rect x="3" y="3" width="18" height="18" rx="1.5"/>
                        <circle cx="8.5" cy="8.5" r="1.5"/>
                        <path d="m21 15-5-5L5 21"/>
                    </svg>
                </div>
            </div>

            {{-- Grupos de atributos --}}
            <div class="modal-var__atributos">
                <template x-if="producto">
                    <div>
                        <template x-for="attr in producto.atributos" :key="attr.id">
                            <div class="modal-var__grupo">
                                <div class="modal-var__grupo-label">
                                    <span x-text="attr.nombre"></span>
                                    <span class="modal-var__grupo-sel"
                                          x-show="seleccion[attr.id]"
                                          x-text="'— ' + (seleccion[attr.id]?.label ?? '')"></span>
                                </div>
                                <div class="modal-var__valores">
                                    <template x-for="val in attr.valores" :key="val.id">
                                        <div :class="attr.tipo === 'color' ? 'modal-var__color-item' : 'modal-var__item'">

                                            {{-- Botón color (círculo) — x-if elimina del DOM para evitar bug de display:contents --}}
                                            <template x-if="attr.tipo === 'color'">
                                                <button
                                                    type="button"
                                                    class="modal-var__valor modal-var__valor--color"
                                                    :class="{
                                                        'modal-var__valor--sel':       seleccion[attr.id]?.id === val.id,
                                                        'modal-var__valor--bloqueado': esValorBloqueado(attr.id, val)
                                                    }"
                                                    :disabled="esValorBloqueado(attr.id, val) && seleccion[attr.id]?.id !== val.id"
                                                    :style="`background-color:${val.valor}`"
                                                    :title="esValorBloqueado(attr.id, val) ? val.label + ' (sin stock)' : val.label"
                                                    @click="seleccionar(attr.id, val)"
                                                ></button>
                                            </template>

                                            {{-- Botón texto (talla, material, etc.) --}}
                                            <template x-if="attr.tipo !== 'color'">
                                                <button
                                                    type="button"
                                                    class="modal-var__valor modal-var__valor--texto"
                                                    :class="{
                                                        'modal-var__valor--sel':       seleccion[attr.id]?.id === val.id,
                                                        'modal-var__valor--bloqueado': esValorBloqueado(attr.id, val)
                                                    }"
                                                    :disabled="esValorBloqueado(attr.id, val) && seleccion[attr.id]?.id !== val.id"
                                                    :title="val.label"
                                                    @click="seleccionar(attr.id, val)"
                                                >
                                                    <span x-text="val.label"></span>
                                                    <span
                                                        x-show="val.precio_adicional > 0"
                                                        class="modal-var__extra-badge"
                                                        x-text="`+S/ ${parseFloat(val.precio_adicional).toFixed(2)}`"
                                                    ></span>
                                                </button>
                                            </template>

                                            {{-- Precio extra debajo del círculo de color --}}
                                            <template x-if="attr.tipo === 'color' && val.precio_adicional > 0">
                                                <span
                                                    class="modal-var__extra-badge"
                                                    x-text="`+S/ ${parseFloat(val.precio_adicional).toFixed(2)}`"
                                                ></span>
                                            </template>

                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <p class="modal-var__no-disp"
                           x-show="producto?.atributos.length > 0 && seleccionCompleta && !varianteCoincidente">
                            Esta combinación no está disponible.
                        </p>
                        <p class="modal-var__no-disp modal-var__no-disp--stock"
                           x-show="varianteSinStock">
                            Esta variante no tiene stock disponible.
                        </p>
                    </div>
                </template>
            </div>

        </div>

        {{-- Footer --}}
        <div class="modal-var__footer">

            {{-- Precio --}}
            <div class="modal-var__precio">
                S/ <span x-text="(parseFloat(precioActual) * cantidad).toFixed(2)"></span>
                <span class="modal-var__precio-unit"
                      x-show="cantidad > 1"
                      x-text="`c/u S/ ${precioActual}`"></span>
            </div>

            {{-- Cantidad --}}
            <div class="modal-var__cant">
                <button type="button" class="modal-var__cant-btn"
                        @click="if(cantidad > 1) cantidad--"
                        :disabled="cantidad <= 1">−</button>
                <span class="modal-var__cant-num" x-text="cantidad"></span>
                <button type="button" class="modal-var__cant-btn"
                        @click="if (stockRestantePromo === null || cantidad < stockRestantePromo) cantidad++"
                        :disabled="stockRestantePromo !== null && cantidad >= stockRestantePromo">+</button>
            </div>

            {{-- Botones acción --}}
            <div class="modal-var__botones">
                <button
                    type="button"
                    class="modal-var__confirmar"
                    :disabled="!disponible"
                    @click="confirmar()"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15" style="flex-shrink:0">
                        <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                    </svg>
                    <span x-text="!seleccionCompleta
                        ? 'Selecciona las opciones'
                        : (varianteSinStock || (stockRestantePromo !== null && stockRestantePromo <= 0)) ? 'Sin stock'
                        : !disponible ? 'No disponible'
                        : 'Agregar al carrito'"></span>
                </button>

                @auth('cliente')
                <button
                    type="button"
                    class="modal-var__confirmar modal-var__confirmar--deseo"
                    :disabled="!disponible"
                    x-show="!producto?.promocion_id"
                    @click="confirmarDeseos()"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15" style="flex-shrink:0">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                    </svg>
                    <span x-text="!seleccionCompleta
                        ? 'Selecciona las opciones'
                        : varianteSinStock ? 'Sin stock'
                        : !disponible ? 'No disponible'
                        : 'Agregar a lista de deseos'"></span>
                </button>
                @endauth
            </div>
        </div>

    </div>
</div>

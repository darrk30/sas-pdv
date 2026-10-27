<div x-data="{
        showBtn: window.location.pathname.includes('punto-de-venta'),
        init() {
            window.addEventListener('livewire:navigated', () => {
                this.showBtn = window.location.pathname.includes('punto-de-venta');
            });
        }
    }">
    {{-- ── Icono topbar (mismo componente que la campanita) ─────────── --}}
    <div x-show="showBtn" x-cloak style="display:none">
        <x-filament::icon-button
            icon="heroicon-o-archive-box"
            color="gray"
            icon-size="lg"
            tooltip="Agregar producto"
            label="Agregar producto"
            wire:click="abrir"
        />
    </div>

    @if($abierto)
    <div class="pdv-overlay" wire:key="modal-crear-producto-rapido">
        <div class="pdv-overlay__backdrop" wire:click="cerrar"></div>

        <div class="pdv-modal cpr-modal"
             x-data="{
                 tab: 'info',
                 controlStock: $wire.entangle('controlStock')
             }">

            {{-- Header --}}
            <div class="pdv-modal__header">
                <div>
                    <h3 class="pdv-modal__titulo">Nuevo Producto</h3>
                    <p class="pdv-modal__subtitulo">Creación rápida</p>
                </div>
                <button class="pdv-modal__cerrar" wire:click="cerrar" type="button">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Tabs --}}
            <div class="cpr-tabs">
                <button type="button" class="cpr-tab" :class="{ 'cpr-tab--active': tab === 'info' }"
                        @click="tab = 'info'">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M9 12h6M9 16h6M7 4h10a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/>
                    </svg>
                    Información
                </button>
                @if($tieneVariantesModulo)
                <button type="button" class="cpr-tab" :class="{ 'cpr-tab--active': tab === 'variantes' }"
                        @click="tab = 'variantes'">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M4 6h16M4 10h16M4 14h10M4 18h6"/>
                    </svg>
                    Variantes
                    @if(count($atributosVariantes) > 0)
                    <span class="cpr-tab-badge">{{ count($atributosVariantes) }}</span>
                    @endif
                </button>
                @endif
            </div>

            {{-- Body --}}
            <div class="pdv-modal__body">

                {{-- ── Tab: Información ─────────────────────────────── --}}
                <div x-show="tab === 'info'" x-cloak>

                    <div class="pdv-field">
                        <label class="pdv-field__label">Nombre <span class="pdv-field__req">*</span></label>
                        <input type="text"
                               class="pdv-field__input @error('nombre') pdv-field__input--error @enderror"
                               wire:model.blur="nombre"
                               placeholder="Ej: Café americano 250ml"
                               autofocus />
                        @error('nombre')<p class="pdv-field__error">{{ $message }}</p>@enderror
                    </div>

                    <div class="cpr-grid-2" style="margin-top:1rem">
                        <div class="pdv-field">
                            <label class="pdv-field__label">Precio venta <span class="pdv-field__req">*</span></label>
                            <div class="cpr-input-prefix">
                                <span class="cpr-prefix">S/</span>
                                <input type="number" step="0.0001" min="0"
                                       class="pdv-field__input cpr-input-with-prefix @error('precioVenta') pdv-field__input--error @enderror"
                                       wire:model.blur="precioVenta"
                                       placeholder="0.00" inputmode="decimal" />
                            </div>
                            @error('precioVenta')<p class="pdv-field__error">{{ $message }}</p>@enderror
                        </div>
                        <div class="pdv-field">
                            <label class="pdv-field__label">Costo</label>
                            <div class="cpr-input-prefix">
                                <span class="cpr-prefix">S/</span>
                                <input type="number" step="0.0001" min="0"
                                       class="pdv-field__input cpr-input-with-prefix"
                                       wire:model.blur="precioCosto"
                                       placeholder="0.00" inputmode="decimal" />
                            </div>
                        </div>
                    </div>

                    <div class="cpr-section" style="margin-top:1rem">
                        <label class="cpr-toggle-row">
                            <div>
                                <span class="cpr-toggle-label">Control de stock</span>
                                <span class="cpr-toggle-hint">Descuenta stock al vender</span>
                            </div>
                            <button type="button" role="switch" :aria-checked="controlStock"
                                    class="cpr-toggle" :class="{ 'cpr-toggle--on': controlStock }"
                                    @click="controlStock = !controlStock"></button>
                        </label>

                        <div x-show="controlStock" x-cloak
                             style="margin-top:.875rem;display:flex;flex-direction:column;gap:.75rem">
                            <label class="cpr-toggle-row">
                                <div>
                                    <span class="cpr-toggle-label">Venta sin stock</span>
                                    <span class="cpr-toggle-hint">Permite vender aunque no haya stock</span>
                                </div>
                                <button type="button" role="switch" :aria-checked="$wire.ventaSinStock"
                                        class="cpr-toggle" :class="{ 'cpr-toggle--on': $wire.ventaSinStock }"
                                        @click="$wire.ventaSinStock = !$wire.ventaSinStock"></button>
                            </label>
                            <div class="pdv-field">
                                <label class="pdv-field__label">Stock inicial</label>
                                <input type="number" step="0.001" min="0"
                                       class="pdv-field__input"
                                       wire:model.blur="stockInicial"
                                       placeholder="0" inputmode="decimal" />
                            </div>
                        </div>
                    </div>

                </div>{{-- /tab info --}}

                {{-- ── Tab: Variantes ────────────────────────────────── --}}
                @if($tieneVariantesModulo)
                <div x-show="tab === 'variantes'" x-cloak>

                    @foreach($atributosVariantes as $ai => $atributo)
                    <div class="cpr-attr-block" wire:key="attr-{{ $ai }}"
                         @if($ai > 0) style="margin-top:.75rem" @endif>

                        {{-- Select atributo + eliminar --}}
                        <div class="cpr-attr-header">
                            <div class="pdv-field" style="flex:1">
                                <label class="pdv-field__label">Atributo</label>
                                <select class="pdv-field__input cpr-select"
                                        wire:model.live="atributosVariantes.{{ $ai }}.atributo_id">
                                    <option value="">— Crear nuevo —</option>
                                    @foreach($atributosDisponibles as $aDisp)
                                        <option value="{{ $aDisp['id'] }}">{{ $aDisp['nombre'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="button" class="cpr-del-btn" style="margin-top:1.5rem"
                                    wire:click="eliminarAtributo({{ $ai }})" title="Quitar atributo">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14">
                                    <path d="M18 6 6 18M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        @if(empty($atributo['atributo_id']))
                        <div class="pdv-field" style="margin-bottom:.5rem">
                            <input type="text" class="pdv-field__input"
                                   wire:model.blur="atributosVariantes.{{ $ai }}.nombre_nuevo"
                                   placeholder="Nombre del nuevo atributo (ej: Tamaño)" />
                        </div>
                        @endif

                        {{-- Valores --}}
                        <div class="cpr-valores-list">
                            <label class="pdv-field__label" style="display:block;margin-bottom:.375rem">Valores</label>

                            @foreach($atributo['valores'] as $vi => $valor)
                            <div class="cpr-valor-block" wire:key="attr-{{ $ai }}-val-{{ $vi }}">
                                {{-- Nombre del valor (más ancho) --}}
                                <div class="pdv-field" style="flex:1;min-width:0">
                                    <select class="pdv-field__input cpr-select"
                                            wire:model.live="atributosVariantes.{{ $ai }}.valores.{{ $vi }}.valor_id">
                                        <option value="">— Crear nuevo valor —</option>
                                        @foreach($atributo['valoresDisponibles'] ?? [] as $vDisp)
                                            <option value="{{ $vDisp['id'] }}">{{ $vDisp['nombre'] }}</option>
                                        @endforeach
                                    </select>
                                    @if(empty($valor['valor_id']))
                                    <input type="text"
                                           class="pdv-field__input @error("atributosVariantes.{$ai}.valores.{$vi}.nombre_nuevo") pdv-field__input--error @enderror"
                                           wire:model.blur="atributosVariantes.{{ $ai }}.valores.{{ $vi }}.nombre_nuevo"
                                           placeholder="Nombre del nuevo valor" />
                                    @error("atributosVariantes.{$ai}.valores.{$vi}.nombre_nuevo")
                                        <p class="pdv-field__error">{{ $message }}</p>
                                    @enderror
                                    @endif
                                </div>
                                {{-- Precio extra (más angosto) --}}
                                <div class="cpr-valor-extra">
                                    <div class="cpr-input-prefix">
                                        <span class="cpr-prefix">+S/</span>
                                        <input type="number" step="0.0001" min="0"
                                               class="pdv-field__input cpr-input-with-prefix-lg"
                                               wire:model.blur="atributosVariantes.{{ $ai }}.valores.{{ $vi }}.precio_extra"
                                               placeholder="0.00" inputmode="decimal" />
                                    </div>
                                </div>
                                {{-- Eliminar: visible solo con 2+ valores; placeholder mantiene la alineación --}}
                                @if(count($atributo['valores']) > 1)
                                <button type="button" class="cpr-del-btn" style="flex-shrink:0;margin-top:.2rem"
                                        wire:click="eliminarValor({{ $ai }}, {{ $vi }})" title="Quitar valor">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14">
                                        <path d="M18 6 6 18M6 6l12 12"/>
                                    </svg>
                                </button>
                                @else
                                <div style="flex-shrink:0;width:1.75rem;margin-top:.2rem"></div>
                                @endif
                            </div>
                            @endforeach

                            <button type="button" class="cpr-agregar-var cpr-agregar-val"
                                    wire:click="agregarValor({{ $ai }})">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="12" height="12">
                                    <path d="M12 4v16m8-8H4"/>
                                </svg>
                                Agregar valor
                            </button>
                        </div>
                    </div>
                    @endforeach

                    <div style="{{ count($atributosVariantes) > 0 ? 'margin-top:.75rem' : '' }}">
                        <button type="button" class="cpr-agregar-var" wire:click="agregarAtributo">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="13" height="13">
                                <path d="M12 4v16m8-8H4"/>
                            </svg>
                            Agregar atributo
                        </button>
                    </div>

                </div>{{-- /tab variantes --}}
                @endif

            </div>{{-- /body --}}

            {{-- Footer --}}
            <div class="pdv-modal__footer cpr-footer">
                <button class="cpr-btn-cancel" wire:click="cerrar" type="button">Cancelar</button>
                <button class="pdv-btn-confirmar" type="button"
                        wire:click="guardar"
                        wire:loading.attr="disabled"
                        wire:loading.class="pdv-btn-confirmar--cargando">
                    <span wire:loading.remove wire:target="guardar">Crear producto</span>
                    <span wire:loading wire:target="guardar">Guardando…</span>
                </button>
            </div>

        </div>
    </div>
    @endif
</div>

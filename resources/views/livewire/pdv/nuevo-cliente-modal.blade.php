<div>
@if($abierto)
    <div class="pdv-overlay" wire:key="modal-nuevo-cliente">
        <div class="pdv-overlay__backdrop" wire:click="cerrar"></div>

        <div class="pdv-modal" style="max-width:44rem;width:100%"
             x-data="{
                 extras: false,
                 tipoDoc: $wire.entangle('ncTipoDoc')
             }">

            {{-- Header --}}
            <div class="pdv-modal__header">
                <div>
                    <h3 class="pdv-modal__titulo">Nuevo Cliente</h3>
                    <p class="pdv-modal__subtitulo">Registro rápido</p>
                </div>
                <button class="pdv-modal__cerrar" wire:click="cerrar">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="pdv-modal__body">

                {{-- ── Campos principales ─────────────────────────── --}}
                <div class="nc-grid">

                    {{-- Tipo documento --}}
                    <div class="pdv-field">
                        <label class="pdv-field__label">Tipo de documento</label>
                        <div class="pdv-doc-tipo">
                            <button
                                class="pdv-doc-tipo__btn"
                                :class="{ 'pdv-doc-tipo__btn--activo': tipoDoc === 'dni' }"
                                @click="tipoDoc = 'dni'"
                                type="button"
                            >DNI</button>
                            <button
                                class="pdv-doc-tipo__btn"
                                :class="{ 'pdv-doc-tipo__btn--activo': tipoDoc === 'ruc' }"
                                @click="tipoDoc = 'ruc'"
                                type="button"
                            >RUC</button>
                        </div>
                    </div>

                    {{-- Número documento --}}
                    <div class="pdv-field">
                        <label class="pdv-field__label">
                            <span x-text="tipoDoc === 'ruc' ? 'RUC' : 'DNI'"></span>
                            <span class="pdv-field__req">*</span>
                        </label>
                        <input
                            type="text"
                            class="pdv-field__input {{ $errors->has('ncNumeroDoc') ? 'pdv-field__input--error' : '' }}"
                            wire:model.blur="ncNumeroDoc"
                            :placeholder="tipoDoc === 'ruc' ? '11 dígitos' : '8 dígitos'"
                            :maxlength="tipoDoc === 'ruc' ? 11 : 8"
                            inputmode="numeric"
                        />
                        @error('ncNumeroDoc')
                            <p class="pdv-field__error">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Nombre --}}
                    <div class="pdv-field">
                        <label class="pdv-field__label">
                            <span x-text="tipoDoc === 'ruc' ? 'Razón Social' : 'Nombre'"></span>
                            <span class="pdv-field__req">*</span>
                        </label>
                        <input
                            type="text"
                            class="pdv-field__input {{ $errors->has('ncNombre') ? 'pdv-field__input--error' : '' }}"
                            wire:model.blur="ncNombre"
                            :placeholder="tipoDoc === 'ruc' ? 'Razón social' : 'Nombre(s)'"
                        />
                        @error('ncNombre')
                            <p class="pdv-field__error">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Teléfono --}}
                    <div class="pdv-field">
                        <label class="pdv-field__label">Teléfono</label>
                        <input
                            type="text"
                            class="pdv-field__input"
                            wire:model.blur="ncTelefono"
                            placeholder="999 888 777"
                            inputmode="tel"
                        />
                    </div>

                </div>

                {{-- ── Acordeón datos opcionales ──────────────────── --}}
                <button
                    type="button"
                    class="nc-acordeon-toggle"
                    @click="extras = !extras"
                >
                    <span class="nc-acordeon-toggle__line"></span>
                    <span class="nc-acordeon-toggle__label">Datos adicionales (opcionales)</span>
                    <span class="nc-acordeon-toggle__line"></span>
                    <svg
                        class="nc-acordeon-toggle__arrow"
                        :class="{ 'nc-acordeon-toggle__arrow--abierto': extras }"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                        width="16" height="16"
                    >
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                </button>

                {{-- Contenido acordeón --}}
                <div
                    class="nc-extras"
                    x-show="extras"
                    x-transition:enter="nc-extras-enter"
                    x-transition:enter-start="nc-extras-hidden"
                    x-transition:enter-end="nc-extras-visible"
                    x-transition:leave="nc-extras-enter"
                    x-transition:leave-start="nc-extras-visible"
                    x-transition:leave-end="nc-extras-hidden"
                    style="display:none"
                >
                    <div class="nc-grid">

                        {{-- Apellidos solo para DNI (Alpine, sin round-trip) --}}
                        <div class="pdv-field" x-show="tipoDoc === 'dni'" x-cloak>
                            <label class="pdv-field__label">Apellidos</label>
                            <input type="text" class="pdv-field__input" wire:model.blur="ncApellidos" placeholder="Apellido paterno y materno"/>
                        </div>

                        <div class="pdv-field" :class="tipoDoc === 'ruc' ? 'nc-grid__full' : ''">
                            <label class="pdv-field__label">Dirección</label>
                            <input type="text" class="pdv-field__input" wire:model.blur="ncDireccion" placeholder="Calle, av., número..."/>
                        </div>

                        <div class="pdv-field">
                            <label class="pdv-field__label">Departamento</label>
                            <input type="text" class="pdv-field__input" wire:model.blur="ncDepartamento" placeholder="Departamento"/>
                        </div>

                        <div class="pdv-field">
                            <label class="pdv-field__label">Provincia</label>
                            <input type="text" class="pdv-field__input" wire:model.blur="ncProvincia" placeholder="Provincia"/>
                        </div>

                        <div class="pdv-field nc-grid__full">
                            <label class="pdv-field__label">Distrito</label>
                            <input type="text" class="pdv-field__input" wire:model.blur="ncDistrito" placeholder="Distrito"/>
                        </div>

                    </div>
                </div>

            </div>

            {{-- Footer --}}
            <div class="pdv-modal__footer">
                <button class="pdv-btn-confirmar" wire:click="crear" type="button">
                    Crear y seleccionar cliente
                </button>
            </div>

        </div>
    </div>
@endif
</div>

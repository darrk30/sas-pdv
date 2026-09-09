<div>
@if($abierto)
    <div class="pdv-overlay" wire:key="modal-nuevo-cliente">
        <div class="pdv-overlay__backdrop" wire:click="cerrar"></div>
        <div class="pdv-modal" style="max-width:26rem;">
            <div class="pdv-modal__header">
                <div>
                    <h3 class="pdv-modal__titulo">Nuevo Cliente</h3>
                    <p class="pdv-modal__subtitulo">Registro rápido</p>
                </div>
                <button class="pdv-modal__cerrar" wire:click="cerrar">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="pdv-modal__body">

                <div class="pdv-field">
                    <label class="pdv-field__label">Tipo de documento</label>
                    <div class="pdv-doc-tipo">
                        <button
                            class="pdv-doc-tipo__btn {{ $ncTipoDoc === 'dni' ? 'pdv-doc-tipo__btn--activo' : '' }}"
                            wire:click="$set('ncTipoDoc', 'dni')"
                        >DNI</button>
                        <button
                            class="pdv-doc-tipo__btn {{ $ncTipoDoc === 'ruc' ? 'pdv-doc-tipo__btn--activo' : '' }}"
                            wire:click="$set('ncTipoDoc', 'ruc')"
                        >RUC</button>
                    </div>
                </div>

                <div class="pdv-field">
                    <label class="pdv-field__label">Número de documento <span class="pdv-field__req">*</span></label>
                    <input
                        type="text"
                        class="pdv-field__input {{ $errors->has('ncNumeroDoc') ? 'pdv-field__input--error' : '' }}"
                        wire:model.live="ncNumeroDoc"
                        placeholder="{{ $ncTipoDoc === 'ruc' ? '11 dígitos' : '8 dígitos' }}"
                        maxlength="{{ $ncTipoDoc === 'ruc' ? 11 : 8 }}"
                    />
                    @error('ncNumeroDoc')
                        <p class="pdv-field__error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="pdv-field">
                    <label class="pdv-field__label">Nombre / Razón Social <span class="pdv-field__req">*</span></label>
                    <input
                        type="text"
                        class="pdv-field__input {{ $errors->has('ncNombre') ? 'pdv-field__input--error' : '' }}"
                        wire:model.live="ncNombre"
                        placeholder="Nombre"
                    />
                    @error('ncNombre')
                        <p class="pdv-field__error">{{ $message }}</p>
                    @enderror
                </div>

                @if($ncTipoDoc === 'dni')
                <div class="pdv-field">
                    <label class="pdv-field__label">Apellidos</label>
                    <input type="text" class="pdv-field__input" wire:model.live="ncApellidos" placeholder="Apellidos"/>
                </div>
                @endif

                <div class="pdv-field">
                    <label class="pdv-field__label">Teléfono</label>
                    <input type="text" class="pdv-field__input" wire:model.live="ncTelefono" placeholder="Ej: 999 888 777"/>
                </div>

                <div class="pdv-field">
                    <label class="pdv-field__label">Dirección</label>
                    <input type="text" class="pdv-field__input" wire:model.live="ncDireccion" placeholder="Calle, av., número..."/>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem">
                    <div class="pdv-field">
                        <label class="pdv-field__label">Departamento</label>
                        <input type="text" class="pdv-field__input" wire:model.live="ncDepartamento" placeholder="Departamento"/>
                    </div>
                    <div class="pdv-field">
                        <label class="pdv-field__label">Provincia</label>
                        <input type="text" class="pdv-field__input" wire:model.live="ncProvincia" placeholder="Provincia"/>
                    </div>
                </div>

                <div class="pdv-field">
                    <label class="pdv-field__label">Distrito</label>
                    <input type="text" class="pdv-field__input" wire:model.live="ncDistrito" placeholder="Distrito"/>
                </div>

            </div>
            <div class="pdv-modal__footer">
                <button class="pdv-btn-confirmar" wire:click="crear">
                    Crear y seleccionar cliente
                </button>
            </div>
        </div>
    </div>
@endif
</div>

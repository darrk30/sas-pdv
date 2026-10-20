<div class="auth-pagina">
    <div class="auth-card auth-card--wide">
        <h1 class="auth-card__titulo">Crear cuenta</h1>
        <p class="auth-card__subtitulo">Completa tus datos para registrarte</p>

        <form wire:submit="registrar" class="auth-form">

            <div class="auth-form__fila">
                <div class="auth-form__grupo">
                    <label class="auth-form__label" for="nombre">Nombre(s) <span class="auth-form__req">*</span></label>
                    <input
                        wire:model="nombre"
                        id="nombre"
                        type="text"
                        class="auth-form__input @error('nombre') auth-form__input--error @enderror"
                        autocomplete="given-name"
                        autofocus
                        placeholder="Ej. Juan"
                    >
                    @error('nombre')
                        <span class="auth-form__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="auth-form__grupo">
                    <label class="auth-form__label" for="apellidos">Apellidos <span class="auth-form__req">*</span></label>
                    <input
                        wire:model="apellidos"
                        id="apellidos"
                        type="text"
                        class="auth-form__input @error('apellidos') auth-form__input--error @enderror"
                        autocomplete="family-name"
                        placeholder="Ej. Pérez García"
                    >
                    @error('apellidos')
                        <span class="auth-form__error">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="auth-form__grupo">
                <label class="auth-form__label" for="dni">DNI <span class="auth-form__req">*</span></label>
                <input
                    wire:model="dni"
                    id="dni"
                    type="text"
                    inputmode="numeric"
                    maxlength="8"
                    class="auth-form__input @error('dni') auth-form__input--error @enderror"
                    placeholder="12345678"
                >
                @error('dni')
                    <span class="auth-form__error">{{ $message }}</span>
                @enderror
            </div>

            <div class="auth-form__grupo">
                <label class="auth-form__label" for="email">Correo electrónico <span class="auth-form__req">*</span></label>
                <input
                    wire:model="email"
                    id="email"
                    type="email"
                    class="auth-form__input @error('email') auth-form__input--error @enderror"
                    autocomplete="email"
                    placeholder="correo@ejemplo.com"
                >
                @error('email')
                    <span class="auth-form__error">{{ $message }}</span>
                @enderror
            </div>

            <div class="auth-form__grupo">
                <label class="auth-form__label" for="password">Contraseña <span class="auth-form__req">*</span></label>
                <div class="auth-form__pass-wrap" x-data="{ show: false }">
                    <input
                        wire:model="password"
                        id="password"
                        :type="show ? 'text' : 'password'"
                        class="auth-form__input @error('password') auth-form__input--error @enderror"
                        autocomplete="new-password"
                        placeholder="Mín. 8 caracteres"
                    >
                    <button type="button" class="auth-form__pass-toggle"
                            @click="show = !show"
                            :aria-label="show ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                            tabindex="-1">
                        <svg x-show="!show" xmlns="http://www.w3.org/2000/svg"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.75" width="18" height="18">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        <svg x-show="show" xmlns="http://www.w3.org/2000/svg"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.75" width="18" height="18">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                            <line x1="1" y1="1" x2="23" y2="23"/>
                        </svg>
                    </button>
                </div>
                @error('password')
                    <span class="auth-form__error">{{ $message }}</span>
                @enderror
                <span class="auth-form__hint">Mínimo 8 caracteres, una mayúscula y un número.</span>
            </div>

            <div class="auth-form__grupo">
                <label class="auth-form__label" for="password_confirmation">Confirmar contraseña <span class="auth-form__req">*</span></label>
                <div class="auth-form__pass-wrap" x-data="{ show: false }">
                    <input
                        wire:model="password_confirmation"
                        id="password_confirmation"
                        :type="show ? 'text' : 'password'"
                        class="auth-form__input"
                        autocomplete="new-password"
                        placeholder="Repite tu contraseña"
                    >
                    <button type="button" class="auth-form__pass-toggle"
                            @click="show = !show"
                            :aria-label="show ? 'Ocultar' : 'Mostrar'"
                            tabindex="-1">
                        <svg x-show="!show" xmlns="http://www.w3.org/2000/svg"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.75" width="18" height="18">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        <svg x-show="show" xmlns="http://www.w3.org/2000/svg"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.75" width="18" height="18">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                            <line x1="1" y1="1" x2="23" y2="23"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="auth-form__btn" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="registrar">Crear cuenta</span>
                <span wire:loading wire:target="registrar">Creando cuenta...</span>
            </button>

        </form>

        <p class="auth-card__pie">
            ¿Ya tienes cuenta?
            <a href="/login" wire:navigate class="auth-card__link">Iniciar sesión</a>
        </p>
    </div>
</div>

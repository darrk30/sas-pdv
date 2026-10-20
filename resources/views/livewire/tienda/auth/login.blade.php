<div class="auth-pagina">
    <div class="auth-card">
        <h1 class="auth-card__titulo">Bienvenido</h1>
        <p class="auth-card__subtitulo">Inicia sesión para continuar</p>

        <form wire:submit="login" class="auth-form">

            <div class="auth-form__grupo">
                <label class="auth-form__label" for="email">Correo electrónico</label>
                <input
                    wire:model="email"
                    id="email"
                    type="email"
                    class="auth-form__input @error('email') auth-form__input--error @enderror"
                    autocomplete="email"
                    autofocus
                    placeholder="correo@ejemplo.com"
                >
                @error('email')
                    <span class="auth-form__error">{{ $message }}</span>
                @enderror
            </div>

            <div class="auth-form__grupo">
                <label class="auth-form__label" for="password">Contraseña</label>
                <div class="auth-form__pass-wrap" x-data="{ show: false }">
                    <input
                        wire:model="password"
                        id="password"
                        :type="show ? 'text' : 'password'"
                        class="auth-form__input @error('password') auth-form__input--error @enderror"
                        autocomplete="current-password"
                        placeholder="Tu contraseña"
                    >
                    <button type="button" class="auth-form__pass-toggle"
                            @click="show = !show"
                            :aria-label="show ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                            tabindex="-1">
                        {{-- Ojo abierto --}}
                        <svg x-show="!show" xmlns="http://www.w3.org/2000/svg"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.75" width="18" height="18">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        {{-- Ojo cerrado --}}
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
            </div>

            <label class="auth-form__check">
                <input wire:model="recordar" type="checkbox">
                Recordarme
            </label>

            <button type="submit" class="auth-form__btn" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="login">Ingresar</span>
                <span wire:loading wire:target="login">Ingresando...</span>
            </button>

        </form>

        <p class="auth-card__pie">
            ¿No tienes cuenta?
            <a href="/registro" wire:navigate class="auth-card__link">Crear cuenta</a>
        </p>
    </div>
</div>

<div class="auth-pagina">
    <div class="auth-card auth-card--wide">
        <h1 class="auth-card__titulo">Crear cuenta</h1>

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
                <input
                    wire:model="password"
                    id="password"
                    type="password"
                    class="auth-form__input @error('password') auth-form__input--error @enderror"
                    autocomplete="new-password"
                    placeholder="Mín. 8 caracteres"
                >
                @error('password')
                    <span class="auth-form__error">{{ $message }}</span>
                @enderror
                <span class="auth-form__hint">Mínimo 8 caracteres, una mayúscula y un número.</span>
            </div>

            <div class="auth-form__grupo">
                <label class="auth-form__label" for="password_confirmation">Confirmar contraseña <span class="auth-form__req">*</span></label>
                <input
                    wire:model="password_confirmation"
                    id="password_confirmation"
                    type="password"
                    class="auth-form__input"
                    autocomplete="new-password"
                    placeholder="Repite tu contraseña"
                >
            </div>

            <button type="submit" class="auth-form__btn" wire:loading.attr="disabled">
                <span wire:loading.remove>Crear cuenta</span>
                <span wire:loading>Creando cuenta...</span>
            </button>

        </form>

        <p class="auth-card__pie">
            ¿Ya tienes cuenta?
            <a href="/login" wire:navigate class="auth-card__link">Iniciar sesión</a>
        </p>
    </div>
</div>

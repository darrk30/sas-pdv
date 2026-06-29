<div class="auth-pagina">
    <div class="auth-card">
        <h1 class="auth-card__titulo">Crear cuenta</h1>

        <form wire:submit="registrar" class="auth-form">

            <div class="auth-form__grupo">
                <label class="auth-form__label" for="nombre">Nombre completo</label>
                <input
                    wire:model="nombre"
                    id="nombre"
                    type="text"
                    class="auth-form__input @error('nombre') auth-form__input--error @enderror"
                    autocomplete="name"
                    autofocus
                >
                @error('nombre')
                    <span class="auth-form__error">{{ $message }}</span>
                @enderror
            </div>

            <div class="auth-form__grupo">
                <label class="auth-form__label" for="email">Correo electrónico</label>
                <input
                    wire:model="email"
                    id="email"
                    type="email"
                    class="auth-form__input @error('email') auth-form__input--error @enderror"
                    autocomplete="email"
                >
                @error('email')
                    <span class="auth-form__error">{{ $message }}</span>
                @enderror
            </div>

            <div class="auth-form__grupo">
                <label class="auth-form__label" for="password">Contraseña</label>
                <input
                    wire:model="password"
                    id="password"
                    type="password"
                    class="auth-form__input @error('password') auth-form__input--error @enderror"
                    autocomplete="new-password"
                >
                @error('password')
                    <span class="auth-form__error">{{ $message }}</span>
                @enderror
            </div>

            <div class="auth-form__grupo">
                <label class="auth-form__label" for="password_confirmation">Confirmar contraseña</label>
                <input
                    wire:model="password_confirmation"
                    id="password_confirmation"
                    type="password"
                    class="auth-form__input"
                    autocomplete="new-password"
                >
            </div>

            <button type="submit" class="auth-form__btn" wire:loading.attr="disabled">
                <span wire:loading.remove>Crear cuenta</span>
                <span wire:loading>Creando cuenta...</span>
            </button>

        </form>

        <p class="auth-card__pie">
            ¿Ya tenés cuenta?
            <a href="/login" wire:navigate class="auth-card__link">Iniciar sesión</a>
        </p>
    </div>
</div>

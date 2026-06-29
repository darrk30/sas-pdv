<div class="auth-pagina">
    <div class="auth-card">
        <h1 class="auth-card__titulo">Iniciar sesión</h1>

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
                    autocomplete="current-password"
                >
                @error('password')
                    <span class="auth-form__error">{{ $message }}</span>
                @enderror
            </div>

            <label class="auth-form__check">
                <input wire:model="recordar" type="checkbox">
                Recordarme
            </label>

            <button type="submit" class="auth-form__btn" wire:loading.attr="disabled">
                <span wire:loading.remove>Ingresar</span>
                <span wire:loading>Ingresando...</span>
            </button>

        </form>

        <p class="auth-card__pie">
            ¿No tenés cuenta?
            <a href="/registro" wire:navigate class="auth-card__link">Crear cuenta</a>
        </p>
    </div>
</div>

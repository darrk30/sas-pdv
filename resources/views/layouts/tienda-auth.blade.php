<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? config('app.name') }}</title>
    @livewireStyles
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('tienda/css/base.css') }}">
    <link rel="stylesheet" href="{{ asset('tienda/css/auth.css') }}">
</head>
<body class="auth-body">

    @php $empresa = app('tienda.empresa'); @endphp

    <header class="auth-encabezado">
        <a href="/" wire:navigate class="auth-encabezado__logo">
            @if ($empresa->logo)
                <img
                    src="{{ Storage::url($empresa->logo) }}"
                    alt="{{ $empresa->nombre }}"
                    class="auth-encabezado__img"
                >
            @else
                {{ $empresa->nombre ?? 'Tienda' }}
            @endif
        </a>
    </header>

    <main class="auth-main">
        {{ $slot }}
    </main>

    @livewireScripts

</body>
</html>

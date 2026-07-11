<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? config('app.name') }}</title>
    @php $empresaIcono = app('tienda.empresa')->icono; @endphp
    @if($empresaIcono)
    <link rel="icon" href="{{ asset('storage/' . $empresaIcono) }}">
    @endif

    {{-- CSS crítico inline: evita flash de contenido sin estilos antes de que carguen los archivos externos --}}
    <style>
        *,*::before,*::after{box-sizing:border-box}
        html{-webkit-text-size-adjust:100%}
        body{margin:0;font-family:'Inter',system-ui,-apple-system,sans-serif;background:#f8f9fa;color:#1e293b;-webkit-font-smoothing:antialiased}
        .auth-body{min-height:100vh;display:flex;flex-direction:column}
        .auth-main{flex:1;display:flex;flex-direction:column}
        .auth-pagina{flex:1;display:flex;align-items:center;justify-content:center;padding:2rem 1rem 3rem}
        [x-cloak]{display:none!important}
    </style>

    @livewireStyles
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    {{-- display=optional: usa Inter si está en caché, sistema si no → sin bloqueo de render --}}
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=optional">
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

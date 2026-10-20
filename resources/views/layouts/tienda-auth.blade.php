<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? config('app.name') }}</title>
    <meta name="robots" content="noindex, nofollow">
    @php $empresa = app('tienda.empresa'); @endphp
    @if($empresa->icono)
    <link rel="icon" href="{{ asset('storage/' . $empresa->icono) }}">
    @endif

    {{-- CSS crítico inline: evita FOUC — debe coincidir con base.css y auth.css --}}
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        html{-webkit-text-size-adjust:100%}
        body{margin:0;font-family:'Plus Jakarta Sans',system-ui,-apple-system,sans-serif;color:#1e2d3d;-webkit-font-smoothing:antialiased}
        .auth-body{min-height:100vh;display:flex;flex-direction:column;background:#f0f5fb}
        .auth-main{flex:1;display:flex;flex-direction:column}
        .auth-pagina{flex:1;display:flex;align-items:center;justify-content:center;padding:2rem 1rem 3rem}
        .auth-card{width:100%;max-width:24rem;background:#fff;border:1px solid #e5e7eb;border-radius:16px;box-shadow:0 4px 24px rgba(30,45,61,.08),0 1px 4px rgba(30,45,61,.04);padding:2.25rem 2rem;position:relative;overflow:hidden}
        .auth-card--wide{max-width:30rem}
        .auth-encabezado{display:flex;justify-content:center;padding:2.5rem 1rem 0}
        [x-cloak]{display:none!important}
    </style>

    @livewireStyles
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="{{ asset('tienda/css/base.css') }}?v=11">
    <link rel="stylesheet" href="{{ asset('tienda/css/auth.css') }}?v=3">
</head>
<body class="auth-body">

    <header class="auth-encabezado">
        <a href="/" wire:navigate class="auth-encabezado__logo">
            @if ($empresa->logo)
                <img src="{{ Storage::url($empresa->logo) }}"
                     alt="{{ $empresa->name }}"
                     class="auth-encabezado__img">
            @else
                <span class="auth-encabezado__nombre">{{ $empresa->name }}</span>
            @endif
        </a>
    </header>

    <main class="auth-main">
        {{ $slot }}
    </main>

    @livewireScripts
    <script>
        document.addEventListener('livewire:navigated', () => window.scrollTo(0, 0));
    </script>

</body>
</html>

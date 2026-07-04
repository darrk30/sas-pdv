<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="cliente-logueado" content="{{ Auth::guard('cliente')->check() ? '1' : '0' }}">
    <meta name="empresa-id" content="{{ app('tienda.empresa')->id }}">
    <title>{{ $title ?? config('app.name') }}</title>

    {{-- PWA --}}
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1e293b">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ app('tienda.empresa')->name ?? config('app.name') }}">
    <link rel="apple-touch-icon" href="/tienda/icons/icon-192.png">

    {{-- ── CSS crítico inline: evita flash de contenido sin estilos ────────── --}}
    <style>
        *,*::before,*::after{box-sizing:border-box}
        html{-webkit-text-size-adjust:100%}
        body{margin:0;font-family:'Inter',system-ui,-apple-system,sans-serif;background:#f8f9fa;color:#1e293b;-webkit-font-smoothing:antialiased}
        .pagina{min-height:80vh}
        [x-cloak]{display:none!important}
    </style>

    {{-- ── Fuentes: preload + carga no bloqueante ─────────────────────────── --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    {{-- font-display=optional: usa Inter si está en caché, sistema si no → sin flash --}}
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=optional">

    {{-- ── CSS global (se necesita en todas las páginas) ─────────────────── --}}
    @livewireStyles
    <link rel="stylesheet" href="{{ asset('tienda/css/base.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('tienda/css/navbar.css') }}">
    <link rel="stylesheet" href="{{ asset('tienda/css/marcas.css') }}">
    <link rel="stylesheet" href="{{ asset('tienda/css/spinner.css') }}">
    <link rel="stylesheet" href="{{ asset('tienda/css/toast.css') }}">
    <link rel="stylesheet" href="{{ asset('tienda/css/carrito.css') }}?v=3">
    <link rel="stylesheet" href="{{ asset('tienda/css/modal-variante.css') }}?v=2">

    {{-- ── CSS específico de cada página (via @push desde las vistas) ─────── --}}
    @stack('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
</head>

<body>

    <livewire:tienda.partials.navbar />
    <livewire:tienda.partials.marcas />

    <main class="pagina">
        {{ $slot }}
    </main>

    <x-tienda.modal-variante />
    <x-tienda.toast />

    {{-- Sincroniza carrito/deseos entre Alpine (localStorage) y DB (Livewire) --}}
    <livewire:tienda.partials.carrito-store />

    @livewireScripts

    {{-- ── JS global: carrito store, fly-to-cart ──────────────────────────── --}}
    <script src="{{ asset('tienda/js/app.js') }}?v=1" data-navigate-once></script>
    {{-- Modal de variantes (necesario en cualquier página con tarjetas de producto) --}}
    <script src="{{ asset('tienda/js/modal-variante.js') }}?v=1" data-navigate-once></script>
    {{-- Componentes Alpine cargados globalmente para que estén en el registry antes de wire:navigate --}}
    <script src="{{ asset('tienda/js/producto-detalle.js') }}?v=2" data-navigate-once></script>
    <script src="{{ asset('tienda/js/lista-deseos.js') }}?v=2" data-navigate-once></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js" data-navigate-once></script>

    {{-- ── JS específico de cada página ───────────────────────────────────── --}}
    @stack('scripts')

    {{-- PWA: registro del service worker --}}
    <script data-navigate-once>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js');
        }
    </script>

</body>

</html>

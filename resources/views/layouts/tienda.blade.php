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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <link rel="stylesheet" href="{{ asset('tienda/css/base.css') }}">
    <link rel="stylesheet" href="{{ asset('tienda/css/navbar.css') }}">
    <link rel="stylesheet" href="{{ asset('tienda/css/marcas.css') }}">
    <link rel="stylesheet" href="{{ asset('tienda/css/spinner.css') }}">
    <link rel="stylesheet" href="{{ asset('tienda/css/catalogo.css') }}">
    <link rel="stylesheet" href="{{ asset('tienda/css/categorias.css') }}">
    <link rel="stylesheet" href="{{ asset('tienda/css/tarjeta.css') }}">
    <link rel="stylesheet" href="{{ asset('tienda/css/paginacion.css') }}">
    <link rel="stylesheet" href="{{ asset('tienda/css/carrito.css') }}">
</head>
<body>

    <livewire:tienda.partials.navbar />
    <livewire:tienda.partials.marcas />

    <main class="pagina">
        {{ $slot }}
    </main>

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    @livewireScripts

</body>
</html>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo no disponible — {{ $empresa->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=optional">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.25rem;
            -webkit-font-smoothing: antialiased;
        }

        .cc-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 1.25rem;
            padding: 3rem 2.5rem;
            max-width: 440px;
            width: 100%;
            text-align: center;
            box-shadow: 0 4px 24px rgba(0,0,0,.06);
        }

        /* Logo o nombre de empresa */
        .cc-logo {
            width: 72px;
            height: 72px;
            border-radius: 1rem;
            object-fit: contain;
            margin: 0 auto 1.25rem;
            display: block;
            border: 1px solid #e2e8f0;
            padding: 6px;
            background: #fff;
        }

        .cc-nombre {
            font-size: 1rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 1.75rem;
            letter-spacing: -.01em;
        }

        /* Icono de mantenimiento */
        .cc-icono-wrap {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #fef3c7;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }

        .cc-icono-wrap svg {
            width: 38px;
            height: 38px;
            color: #d97706;
        }

        .cc-titulo {
            font-size: 1.375rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: .625rem;
            letter-spacing: -.02em;
            line-height: 1.3;
        }

        .cc-subtitulo {
            font-size: .9375rem;
            color: #64748b;
            line-height: 1.65;
            margin-bottom: 2rem;
        }

        /* Divider */
        .cc-divider {
            height: 1px;
            background: #f1f5f9;
            margin: 0 -2.5rem 1.5rem;
        }

        /* Contacto */
        .cc-contacto {
            display: flex;
            flex-direction: column;
            gap: .5rem;
        }

        .cc-contacto-label {
            font-size: .75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #94a3b8;
            margin-bottom: .25rem;
        }

        .cc-contacto-item {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            font-size: .875rem;
            color: #475569;
            text-decoration: none;
        }

        .cc-contacto-item svg {
            width: 15px;
            height: 15px;
            flex-shrink: 0;
            color: #94a3b8;
        }

        .cc-contacto-item:hover { color: #0f172a; }

        /* Footer */
        .cc-footer {
            margin-top: 2.5rem;
            font-size: .75rem;
            color: #cbd5e1;
        }

        @media (max-width: 480px) {
            .cc-card { padding: 2rem 1.5rem; }
            .cc-divider { margin: 0 -1.5rem 1.5rem; }
        }
    </style>
</head>
<body>

<div class="cc-card">

    {{-- Logo o nombre --}}
    @if ($empresa->logo)
        <img src="{{ asset('storage/' . $empresa->logo) }}"
             alt="{{ $empresa->name }}"
             class="cc-logo">
    @else
        <p class="cc-nombre">{{ $empresa->name }}</p>
    @endif

    {{-- Ícono --}}
    <div class="cc-icono-wrap">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/>
            <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
    </div>

    <h1 class="cc-titulo">Catálogo no disponible</h1>
    <p class="cc-subtitulo">
        Estamos realizando actualizaciones en nuestra tienda.<br>
        Vuelve pronto, ¡lo haremos mejor para ti!
    </p>

    {{-- Contacto si hay datos --}}
    @if ($empresa->telefono || $empresa->email)
        <div class="cc-divider"></div>
        <div class="cc-contacto">
            <span class="cc-contacto-label">¿Tienes una consulta?</span>

            @if ($empresa->telefono)
                <a href="https://wa.me/{{ preg_replace('/\D/', '', $empresa->telefono) }}"
                   target="_blank"
                   rel="noopener"
                   class="cc-contacto-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.62 3.38 2 2 0 0 1 3.6 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.6a16 16 0 0 0 6 6l.96-.96a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
                    </svg>
                    {{ $empresa->telefono }}
                </a>
            @endif

            @if ($empresa->email)
                <a href="mailto:{{ $empresa->email }}" class="cc-contacto-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                    {{ $empresa->email }}
                </a>
            @endif
        </div>
    @endif

</div>

<p class="cc-footer">{{ $empresa->name }} &copy; {{ date('Y') }}</p>

</body>
</html>

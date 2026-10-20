@php
    $empresa  = app('tienda.empresa');
    $ubicacion = collect([$empresa->distrito, $empresa->provincia, $empresa->departamento])
        ->filter()->implode(', ');
    $año = date('Y');
    $tieneSocial = $empresa->facebook || $empresa->instagram || $empresa->tiktok;
@endphp

<footer class="site-footer">
    <div class="site-footer__inner">

        {{-- Bloque empresa --}}
        <div class="site-footer__empresa">
            @if ($empresa->logo)
                <img src="{{ asset('storage/' . $empresa->logo) }}"
                     alt="{{ $empresa->name }}"
                     class="site-footer__logo">
            @else
                <span class="site-footer__nombre-grande">{{ $empresa->name }}</span>
            @endif

            @if ($empresa->rubro)
                <span class="site-footer__rubro">{{ $empresa->rubro }}</span>
            @endif

            @if ($empresa->descripcion)
                <p class="site-footer__desc">{{ $empresa->descripcion }}</p>
            @endif

            @if ($tieneSocial)
                <div class="site-footer__socials">
                    @if ($empresa->facebook)
                        <a href="{{ $empresa->facebook }}" target="_blank" rel="noopener"
                           class="site-footer__social" title="Facebook">
                            {{-- Facebook --}}
                            <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
                                <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                            </svg>
                        </a>
                    @endif
                    @if ($empresa->instagram)
                        <a href="{{ $empresa->instagram }}" target="_blank" rel="noopener"
                           class="site-footer__social" title="Instagram">
                            {{-- Instagram --}}
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" width="18" height="18">
                                <rect x="2" y="2" width="20" height="20" rx="5" ry="5"/>
                                <circle cx="12" cy="12" r="4"/>
                                <circle cx="17.5" cy="6.5" r="0.5" fill="currentColor" stroke="none"/>
                            </svg>
                        </a>
                    @endif
                    @if ($empresa->tiktok)
                        <a href="{{ $empresa->tiktok }}" target="_blank" rel="noopener"
                           class="site-footer__social" title="TikTok">
                            {{-- TikTok --}}
                            <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
                                <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1-2.89-2.89 2.89 2.89 0 0 1 2.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 0 0-.79-.05 6.34 6.34 0 0 0-6.34 6.34 6.34 6.34 0 0 0 6.34 6.34 6.34 6.34 0 0 0 6.33-6.34V8.69a8.18 8.18 0 0 0 4.78 1.52V6.75a4.85 4.85 0 0 1-1.01-.06z"/>
                            </svg>
                        </a>
                    @endif
                </div>
            @endif
        </div>

        {{-- Info de contacto --}}
        <div class="site-footer__info">
            <h3 class="site-footer__seccion-titulo">Información</h3>

            @if ($empresa->ruc)
                <div class="site-footer__dato">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
                         width="14" height="14" class="site-footer__dato-icon">
                        <rect x="2" y="7" width="20" height="14" rx="2"/>
                        <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
                    </svg>
                    <span>RUC: {{ $empresa->ruc }}</span>
                </div>
            @endif

            @if ($empresa->direccion)
                <div class="site-footer__dato">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
                         width="14" height="14" class="site-footer__dato-icon">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                        <circle cx="12" cy="9" r="2.5"/>
                    </svg>
                    <span>{{ $empresa->direccion }}{{ $ubicacion ? ', ' . $ubicacion : '' }}</span>
                </div>
            @endif
        </div>

        {{-- Contacto --}}
        <div class="site-footer__contacto">
            <h3 class="site-footer__seccion-titulo">Contacto</h3>

            @if ($empresa->telefono)
                <a href="https://wa.me/51{{ preg_replace('/\D/', '', $empresa->telefono) }}"
                   class="site-footer__dato site-footer__dato--link"
                   target="_blank" rel="noopener">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
                         width="14" height="14" class="site-footer__dato-icon">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2A19.79 19.79 0 0 1 11.6 18.9a19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
                    </svg>
                    <span>{{ $empresa->telefono }}</span>
                </a>
            @endif

            @if ($empresa->email)
                <a href="mailto:{{ $empresa->email }}"
                   class="site-footer__dato site-footer__dato--link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
                         width="14" height="14" class="site-footer__dato-icon">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                    <span>{{ $empresa->email }}</span>
                </a>
            @endif
        </div>

    </div>

    {{-- Barra inferior --}}
    <div class="site-footer__bottom">
        <span class="site-footer__copy">
            © {{ $año }} {{ $empresa->name }}. Todos los derechos reservados.
        </span>
        <a href="https://tukipu.cloud"
           target="_blank"
           rel="noopener"
           class="site-footer__powered">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
                 width="13" height="13" class="site-footer__powered-icon">
                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
            </svg>
            Con tecnología
            <strong>tukipu.cloud</strong>
        </a>
    </div>
</footer>

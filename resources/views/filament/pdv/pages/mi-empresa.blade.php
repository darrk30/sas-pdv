<x-filament-panels::page>

<link rel="stylesheet" href="{{ asset('css/mi-empresa.css') }}?v={{ filemtime(public_path('css/mi-empresa.css')) }}">

    <div class="me-page-wrapper">

        {{-- ── Hero ────────────────────────────────────────────────────────── --}}
        <div class="me-hero">
            <div class="me-hero-icon">
                <x-heroicon-o-building-office-2 class="me-hero-svg" />
            </div>
            <div>
                <h2 class="me-hero-title">Configura tu empresa</h2>
                <p class="me-hero-sub">Mantén actualizados los datos de tu empresa. Los cambios se reflejan en comprobantes, el catálogo y el facturador electrónico.</p>
            </div>
        </div>

        {{-- ── Formulario ───────────────────────────────────────────────────── --}}
        <form wire:submit="save" class="me-form">
            {{ $this->form }}

            <div class="me-footer">
                <x-filament::button
                    type="submit"
                    size="lg"
                    icon="heroicon-o-check"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>Guardar cambios</span>
                    <span wire:loading>Guardando…</span>
                </x-filament::button>
            </div>
        </form>

    </div>

    {{-- Listener para copiar al portapapeles --}}
    <span
        x-data
        x-on:tukipu-copiar.window="
            const texto = $event.detail.texto ?? '';
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(texto).catch(() => fallbackCopy(texto));
            } else {
                fallbackCopy(texto);
            }
            function fallbackCopy(t) {
                const ta = document.createElement('textarea');
                ta.value = t;
                ta.style.cssText = 'position:fixed;opacity:0;top:0;left:0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            }
        "
    ></span>

</x-filament-panels::page>

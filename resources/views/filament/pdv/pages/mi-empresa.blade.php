<x-filament-panels::page>

    <div class="me-page-wrapper">

        {{-- ── Encabezado informativo ──────────────────────────────────────── --}}
        <div class="me-hero">
            <div class="me-hero-icon">
                <x-heroicon-o-building-office-2 class="me-hero-svg" />
            </div>
            <div>
                <h2 class="me-hero-title">Configura tu empresa</h2>
                <p class="me-hero-sub">Mantén actualizados los datos de tu empresa. Los cambios se reflejan en el catálogo, documentos y el panel.</p>
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

    <style>
        /* ── Wrapper ──────────────────────────────────────────────────────── */
        .me-page-wrapper {
            max-width: 820px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 1.75rem;
        }

        /* ── Hero header ──────────────────────────────────────────────────── */
        .me-hero {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: linear-gradient(135deg, #46449e 0%, #6c63d6 100%);
            border-radius: 1rem;
            padding: 1.25rem 1.5rem;
            color: #fff;
        }

        .me-hero-icon {
            flex-shrink: 0;
            background: rgba(255,255,255,.15);
            border-radius: .75rem;
            padding: .6rem;
            display: flex;
        }

        .me-hero-svg {
            width: 2rem;
            height: 2rem;
            color: #fff;
        }

        .me-hero-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0 0 .2rem;
        }

        .me-hero-sub {
            font-size: .825rem;
            opacity: .85;
            margin: 0;
            line-height: 1.4;
        }

        /* ── Form ─────────────────────────────────────────────────────────── */
        .me-form {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        /* ── Footer / submit ──────────────────────────────────────────────── */
        .me-footer {
            display: flex;
            justify-content: flex-end;
            padding-top: .5rem;
        }
    </style>

</x-filament-panels::page>

<x-filament-panels::page>

    <div class="msp-wrapper">

        {{-- Hero ──────────────────────────────────────────────────────────── --}}
        <div class="msp-hero">
            <div class="msp-hero-icon">
                <x-heroicon-o-credit-card class="msp-hero-svg" />
            </div>
            <div>
                <h2 class="msp-hero-title">Mi Suscripción</h2>
                <p class="msp-hero-sub">Consulta el estado de tu plan, las fechas de vencimiento y registra tus comprobantes de pago para que el administrador los verifique.</p>
            </div>
        </div>

        @php
            $sus  = $this->suscripcion;
            $plan = $sus?->plan;
            $hoy  = now()->startOfDay();

            $diasRestantes = null;
            $vencida       = false;
            $porVencer     = false;

            if ($sus) {
                $fin           = \Carbon\Carbon::parse($sus->fecha_fin)->startOfDay();
                $diasRestantes = (int) $hoy->diffInDays($fin, false);
                $vencida       = $diasRestantes < 0;
                $porVencer     = ! $vencida && $diasRestantes <= 7;
            }
        @endphp

        @if (! $sus || ! $plan)

            {{-- Sin suscripción ──────────────────────────────────────────── --}}
            <div class="msp-empty">
                <x-heroicon-o-exclamation-triangle class="msp-empty-icon" />
                <p class="msp-empty-text">Tu empresa aún no tiene un plan asignado.<br>Contacta al administrador del sistema para activar tu suscripción.</p>
            </div>

        @else

            {{-- Grid principal ───────────────────────────────────────────── --}}
            <div class="msp-grid">

                {{-- Tarjeta del plan ─────────────────────────────────────── --}}
                <div class="msp-card msp-card-plan">
                    <div class="msp-plan-header">
                        <span class="msp-plan-name">{{ $plan->nombre }}</span>
                        <span class="msp-badge msp-badge-{{ $sus->estado->value }}">
                            {{ $sus->estado->getLabel() }}
                        </span>
                    </div>

                    <div class="msp-plan-price">
                        <span class="msp-price-sym">S/</span>
                        <span class="msp-price-amount">{{ number_format((float) $sus->precio_pagado, 2) }}</span>
                        <span class="msp-price-cycle">/ {{ $plan->ciclo_facturacion }}</span>
                    </div>

                    @if ($plan->descripcion)
                        <p class="msp-plan-desc">{{ $plan->descripcion }}</p>
                    @endif

                    <ul class="msp-features">
                        <li class="msp-feature">
                            <x-heroicon-o-users class="msp-feature-ico" />
                            Hasta <strong>{{ $plan->maximo_usuarios }}</strong> usuario(s)
                        </li>
                        <li class="msp-feature">
                            <x-heroicon-o-building-storefront class="msp-feature-ico" />
                            Hasta <strong>{{ $plan->maximo_locales }}</strong> local(es)
                        </li>
                        <li class="msp-feature msp-feature--{{ $plan->tiene_catalogo_web ? 'on' : 'off' }}">
                            @if ($plan->tiene_catalogo_web)
                                <x-heroicon-o-check-circle class="msp-feature-ico" />
                            @else
                                <x-heroicon-o-x-circle class="msp-feature-ico msp-feature-ico--off" />
                            @endif
                            Tienda web / Catálogo online
                        </li>
                        @if ($plan->tiene_variantes)
                            <li class="msp-feature">
                                <x-heroicon-o-check-circle class="msp-feature-ico" />
                                Variantes de productos
                            </li>
                        @endif
                    </ul>
                </div>

                {{-- Tarjeta de vigencia ──────────────────────────────────── --}}
                <div class="msp-card msp-card-dates">
                    <h3 class="msp-card-title">Vigencia de la suscripción</h3>

                    <div class="msp-date-row">
                        <span class="msp-date-lbl">Inicio</span>
                        <span class="msp-date-val">{{ \Carbon\Carbon::parse($sus->fecha_inicio)->format('d/m/Y') }}</span>
                    </div>
                    <div class="msp-date-row">
                        <span class="msp-date-lbl">Vencimiento</span>
                        <span class="msp-date-val msp-date-fin">{{ \Carbon\Carbon::parse($sus->fecha_fin)->format('d/m/Y') }}</span>
                    </div>

                    <div class="msp-countdown msp-countdown-{{ $vencida ? 'vencida' : ($porVencer ? 'alerta' : 'ok') }}">
                        @if ($vencida)
                            <x-heroicon-o-x-circle class="msp-cd-ico" />
                            <span>Suscripción vencida hace <strong>{{ abs($diasRestantes) }}</strong> día(s)</span>
                        @elseif ($porVencer)
                            <x-heroicon-o-clock class="msp-cd-ico" />
                            <span>Vence en <strong>{{ $diasRestantes }}</strong> día(s) — ¡Renueva pronto!</span>
                        @else
                            <x-heroicon-o-check-circle class="msp-cd-ico" />
                            <span>Activa — <strong>{{ $diasRestantes }}</strong> días restantes</span>
                        @endif
                    </div>
                </div>

            </div>

            {{-- Historial de pagos ───────────────────────────────────────── --}}
            <div class="msp-card msp-card-history">
                <h3 class="msp-card-title">Historial de pagos registrados</h3>

                @php $pagos = $this->pagos; @endphp

                @if ($pagos->isEmpty())
                    <p class="msp-no-data">No hay pagos registrados aún. Usa el botón <em>"Registrar comprobante de pago"</em> cuando realices tu próximo pago.</p>
                @else
                    <div class="msp-table-wrap">
                        <table class="msp-table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th class="msp-th-r">Monto</th>
                                    <th>Método</th>
                                    <th>N° Operación</th>
                                    <th>Comprobante</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pagos as $pago)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($pago->fecha_pago)->format('d/m/Y H:i') }}</td>
                                        <td class="msp-td-monto">S/ {{ number_format((float) $pago->monto, 2) }}</td>
                                        <td>{{ ucfirst($pago->metodo_pago) }}</td>
                                        <td class="msp-td-ref">{{ $pago->referencia ?? '—' }}</td>
                                        <td>
                                            @if ($pago->path_url)
                                                <a href="{{ asset('storage/' . $pago->path_url) }}" target="_blank" class="msp-voucher-link">
                                                    <x-heroicon-o-document-magnifying-glass class="msp-doc-ico" /> Ver
                                                </a>
                                            @else
                                                <span class="msp-no-voucher">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        @endif

    </div>

    <style>
        /* ── Tokens de color — modo claro (default) ─────────────────────── */
        :root {
            --msp-bg-card:     #ffffff;
            --msp-border:      #e5e7eb;
            --msp-border-soft: #f3f4f6;
            --msp-text:        #111827;
            --msp-text-muted:  #6b7280;
            --msp-text-subtle: #9ca3af;
            --msp-accent:      #46449e;
            --msp-ok-bg:       #dcfce7; --msp-ok-fg:   #15803d;
            --msp-warn-bg:     #fef3c7; --msp-warn-fg: #92400e;
            --msp-err-bg:      #fee2e2; --msp-err-fg:  #b91c1c;
            --msp-gray-bg:     #f3f4f6; --msp-gray-fg: #6b7280;
        }
        /* Filament añade .dark al <html> cuando el usuario activa el modo oscuro */
        .dark {
            --msp-bg-card:     #1f2937;
            --msp-border:      #374151;
            --msp-border-soft: #2d3748;
            --msp-text:        #f9fafb;
            --msp-text-muted:  #9ca3af;
            --msp-text-subtle: #6b7280;
            --msp-accent:      #a5b4fc;
            --msp-ok-bg:       #14532d; --msp-ok-fg:   #86efac;
            --msp-warn-bg:     #78350f; --msp-warn-fg: #fcd34d;
            --msp-err-bg:      #7f1d1d; --msp-err-fg:  #fca5a5;
            --msp-gray-bg:     #374151; --msp-gray-fg: #9ca3af;
        }

        /* ── Wrapper ─────────────────────────────────────────────────────── */
        .msp-wrapper {
            max-width: 900px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        /* ── Hero ────────────────────────────────────────────────────────── */
        .msp-hero {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: linear-gradient(135deg, #46449e 0%, #6c63d6 100%);
            border-radius: 1rem;
            padding: 1.25rem 1.5rem;
            color: #fff;
        }
        .msp-hero-icon {
            flex-shrink: 0;
            background: rgba(255,255,255,.15);
            border-radius: .75rem;
            padding: .6rem;
            display: flex;
        }
        .msp-hero-svg   { width: 2rem; height: 2rem; color: #fff; }
        .msp-hero-title { font-size: 1.1rem; font-weight: 700; margin: 0 0 .2rem; }
        .msp-hero-sub   { font-size: .825rem; opacity: .85; margin: 0; line-height: 1.45; }

        /* ── Grid ────────────────────────────────────────────────────────── */
        .msp-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
        }
        @media (max-width: 640px) { .msp-grid { grid-template-columns: 1fr; } }

        /* ── Cards ───────────────────────────────────────────────────────── */
        .msp-card {
            background: var(--msp-bg-card);
            border: 1px solid var(--msp-border);
            border-radius: 1rem;
            padding: 1.25rem 1.5rem;
            color: var(--msp-text);
        }
        .msp-card-title {
            font-size: .8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--msp-text-subtle);
            margin: 0 0 1rem;
        }

        /* ── Plan card ───────────────────────────────────────────────────── */
        .msp-plan-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: .75rem;
        }
        .msp-plan-name { font-size: 1.3rem; font-weight: 800; color: var(--msp-text); }

        .msp-badge {
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            padding: .2rem .65rem;
            border-radius: 999px;
        }
        .msp-badge-activo    { background: var(--msp-ok-bg);   color: var(--msp-ok-fg); }
        .msp-badge-inactivo  { background: var(--msp-err-bg);  color: var(--msp-err-fg); }
        .msp-badge-archivado { background: var(--msp-gray-bg); color: var(--msp-gray-fg); }

        .msp-plan-price {
            display: flex;
            align-items: baseline;
            gap: .2rem;
            margin-bottom: .5rem;
        }
        .msp-price-sym    { font-size: 1.1rem; font-weight: 600; color: var(--msp-text); }
        .msp-price-amount { font-size: 2.4rem; font-weight: 800; line-height: 1; color: var(--msp-text); }
        .msp-price-cycle  { font-size: .875rem; color: var(--msp-text-subtle); margin-left: .1rem; }

        .msp-plan-desc { font-size: .85rem; color: var(--msp-text-muted); margin: 0 0 .75rem; }

        .msp-features {
            list-style: none;
            margin: .75rem 0 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: .5rem;
        }
        .msp-feature {
            display: flex;
            align-items: center;
            gap: .45rem;
            font-size: .85rem;
            color: var(--msp-text);
        }
        .msp-feature-ico          { width: 1rem; height: 1rem; flex-shrink: 0; color: var(--msp-accent); }
        .msp-feature-ico--off     { color: var(--msp-err-fg); }
        .msp-feature--off         { opacity: .5; }

        /* ── Dates card ──────────────────────────────────────────────────── */
        .msp-date-row {
            display: flex;
            justify-content: space-between;
            padding: .45rem 0;
            border-bottom: 1px solid var(--msp-border-soft);
            font-size: .875rem;
        }
        .msp-date-row:last-of-type { border-bottom: none; }
        .msp-date-lbl { color: var(--msp-text-subtle); }
        .msp-date-val { font-weight: 600; color: var(--msp-text); }
        .msp-date-fin { color: var(--msp-accent); }

        .msp-countdown {
            margin-top: 1rem;
            display: flex;
            align-items: center;
            gap: .5rem;
            padding: .65rem 1rem;
            border-radius: .75rem;
            font-size: .85rem;
            font-weight: 500;
        }
        .msp-countdown-ok      { background: var(--msp-ok-bg);   color: var(--msp-ok-fg); }
        .msp-countdown-alerta  { background: var(--msp-warn-bg); color: var(--msp-warn-fg); }
        .msp-countdown-vencida { background: var(--msp-err-bg);  color: var(--msp-err-fg); }
        .msp-cd-ico { width: 1.1rem; height: 1.1rem; flex-shrink: 0; }

        /* ── History ─────────────────────────────────────────────────────── */
        .msp-card-history { grid-column: 1 / -1; }
        .msp-no-data { font-size: .875rem; color: var(--msp-text-muted); line-height: 1.6; }

        .msp-table-wrap { overflow-x: auto; }
        .msp-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .85rem;
            color: var(--msp-text);
        }
        .msp-table th {
            text-align: left;
            padding: .5rem .75rem;
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--msp-text-subtle);
            border-bottom: 1px solid var(--msp-border);
            white-space: nowrap;
        }
        .msp-th-r { text-align: right; }
        .msp-table td {
            padding: .65rem .75rem;
            border-bottom: 1px solid var(--msp-border-soft);
            vertical-align: middle;
        }
        .msp-table tr:last-child td { border-bottom: none; }
        .msp-td-monto { font-weight: 700; text-align: right; font-variant-numeric: tabular-nums; }
        .msp-td-ref   { font-family: monospace; font-size: .8rem; color: var(--msp-text-muted); }

        .msp-voucher-link {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            color: var(--msp-accent);
            font-weight: 600;
            font-size: .8rem;
            text-decoration: underline;
        }
        .msp-doc-ico    { width: .95rem; height: .95rem; }
        .msp-no-voucher { color: var(--msp-border); }

        /* ── Empty state ─────────────────────────────────────────────────── */
        .msp-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
            padding: 3rem 2rem;
            text-align: center;
            background: var(--msp-bg-card);
            border: 1px solid var(--msp-border);
            border-radius: 1rem;
        }
        .msp-empty-icon { width: 3rem; height: 3rem; color: #f59e0b; }
        .msp-empty-text { font-size: .9rem; color: var(--msp-text-muted); line-height: 1.6; }
    </style>

</x-filament-panels::page>

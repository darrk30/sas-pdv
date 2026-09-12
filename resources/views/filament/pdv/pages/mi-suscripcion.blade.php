<x-filament-panels::page>
    <link rel="stylesheet" href="{{ asset('css/mi-suscripcion.css') }}?v={{ filemtime(public_path('css/mi-suscripcion.css')) }}">
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
                            <span>Activa — <strong>{{ $diasRestantes }}</strong> día(s) restante(s)</span>
                        @endif
                    </div>
                </div>

            </div>

            {{-- Historial de pagos (tabla Filament) ────────────────────── --}}
            {{ $this->table }}

        @endif

    </div>



</x-filament-panels::page>

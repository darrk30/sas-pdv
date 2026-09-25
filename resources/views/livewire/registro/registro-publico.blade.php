<div class="rg-page">

    {{-- ── Botón volver --}}
    <a href="{{ url('/') }}" class="rg-back-btn" title="Volver al inicio">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M19 12H5M12 5l-7 7 7 7"/>
        </svg>
        <span>Inicio</span>
    </a>

    {{-- ── HERO ──────────────────────────────────────── --}}
    <aside class="rg-hero">
        <img src="{{ asset('img/registrotukipu.png') }}" alt="{{ config('app.name') }}" class="rg-hero-img">
    </aside>

    {{-- ── CONTENT ────────────────────────────────────── --}}
    <main class="rg-content">
        <div class="rg-shell">

            {{-- Step indicator --}}
            @if($paso < 4)
            @php $stepFill = match($paso) { 2 => '50%', 3 => '100%', default => '0%' }; @endphp
            <div class="rg-steps">
                <div class="rg-steps-track">
                    <div class="rg-steps-fill" style="width:{{ $stepFill }}"></div>
                </div>
                @foreach([1 => 'Crear cuenta', 2 => 'Elegir plan', 3 => 'Tu empresa'] as $n => $label)
                <div class="rg-step {{ $paso > $n ? 'done' : ($paso === $n ? 'active' : '') }}">
                    <div class="rg-step-circle">{{ $paso > $n ? '✓' : $n }}</div>
                    <div class="rg-step-label">{{ $label }}</div>
                </div>
                @endforeach
            </div>
            @endif

            {{-- Card --}}
            <div class="rg-card">

                {{-- PASO 1: Cuenta --}}
                @if($paso === 1)
                <span class="rg-eyebrow">Paso 1 de 3</span>
                <h2 class="rg-card-title">Crea tu cuenta</h2>
                <p class="rg-card-sub">Primero verificamos tu correo para asegurarnos de que es tuyo.</p>

                {{-- FASE A: nombre + email + botón enviar código --}}
                @if(! $codigoEnviado && ! $codigoVerificado)
                <form wire:submit="enviarCodigo" autocomplete="on">
                <div class="rg-grid">
                    <div class="rg-field-wrap">
                        <label class="rg-label">Nombre <span class="rg-required">*</span></label>
                        <input wire:model="nombre" type="text" placeholder="Ej. Juan" autocomplete="given-name" class="rg-field">
                        @error('nombre')<p class="rg-field-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="rg-field-wrap">
                        <label class="rg-label">Apellidos <span class="rg-required">*</span></label>
                        <input wire:model="apellidos" type="text" placeholder="Ej. Pérez García" autocomplete="family-name" class="rg-field">
                        @error('apellidos')<p class="rg-field-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="rg-field-wrap rg-col2">
                        <label class="rg-label">Correo electrónico <span class="rg-required">*</span></label>
                        <input wire:model="email" type="email" placeholder="juan@empresa.com" autocomplete="email" class="rg-field">
                        @error('email')<p class="rg-field-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="rg-actions" style="border-top:0;margin-top:20px;padding-top:0;justify-content:flex-end">
                    <button type="submit" wire:loading.attr="disabled" class="rg-btn rg-btn-primary">
                        <span wire:loading.remove wire:target="enviarCodigo">Enviar código de verificación →</span>
                        <span wire:loading wire:target="enviarCodigo">Enviando...</span>
                    </button>
                </div>
                </form>
                @endif

                {{-- FASE B: ingresar código --}}
                @if($codigoEnviado && ! $codigoVerificado)
                <div class="rg-verify-box">
                    <div class="rg-verify-icon">{{ $codigoYaExistia ? '🔒' : '✉' }}</div>
                    @if($codigoYaExistia)
                    <p class="rg-verify-msg">Ya enviamos un código a <strong>{{ $email }}</strong>. Ingrésalo abajo — sigue vigente. Si no lo encuentras, usa <em>Reenviar código</em>.</p>
                    @else
                    <p class="rg-verify-msg">Enviamos un código de 6 dígitos a <strong>{{ $email }}</strong>. Revisa tu bandeja de entrada (y spam).</p>
                    @endif
                </div>
                <form wire:submit="verificarCodigo">
                <div class="rg-field-wrap" style="margin-top:16px">
                    <label class="rg-label">Código de verificación <span class="rg-required">*</span></label>
                    <input wire:model.live="codigoIngresado" type="text" inputmode="numeric" maxlength="6"
                           placeholder="000000" class="rg-field rg-code-input" autocomplete="one-time-code">
                    @error('codigoIngresado')<p class="rg-field-error">{{ $message }}</p>@enderror
                </div>
                <div class="rg-actions" style="border-top:0;margin-top:16px;padding-top:0;justify-content:space-between">
                    <button type="button" wire:click="reenviarCodigo" wire:loading.attr="disabled" class="rg-btn rg-btn-secondary" style="font-size:12px">
                        <span wire:loading.remove wire:target="reenviarCodigo">Reenviar código</span>
                        <span wire:loading wire:target="reenviarCodigo">Reenviando...</span>
                    </button>
                    <button type="submit" wire:loading.attr="disabled" class="rg-btn rg-btn-primary">
                        <span wire:loading.remove wire:target="verificarCodigo">Verificar →</span>
                        <span wire:loading wire:target="verificarCodigo">Verificando...</span>
                    </button>
                </div>
                </form>
                @endif

                {{-- FASE C: correo verificado → contraseña --}}
                @if($codigoVerificado)
                <div class="rg-verified-badge">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                    Correo verificado: <strong>{{ $email }}</strong>
                </div>
                <form wire:submit="siguientePaso" autocomplete="on">
                <div class="rg-grid" style="margin-top:18px">
                    <div class="rg-field-wrap" x-data="{ ver: false }">
                        <label class="rg-label">Contraseña <span class="rg-required">*</span></label>
                        <div class="rg-input-eye">
                            <input wire:model="password" :type="ver ? 'text' : 'password'"
                                   placeholder="Mínimo 8 caracteres" autocomplete="new-password" class="rg-field">
                            <button type="button" @click="ver = !ver" class="rg-eye-btn" tabindex="-1">
                                <svg x-show="!ver" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg x-show="ver"  width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                        {{-- Indicador fortaleza --}}
                        <div x-data="{
                            get fuerza() {
                                const v = $wire.password;
                                if (!v) return 0;
                                let s = 0;
                                if (v.length >= 8) s++;
                                if (/[A-Z]/.test(v)) s++;
                                if (/[^A-Za-z0-9]/.test(v)) s++;
                                return s;
                            }
                        }" class="rg-strength" x-show="$wire.password.length > 0">
                            <div class="rg-strength-bars">
                                <div class="rg-strength-bar" :class="fuerza >= 1 ? (fuerza === 1 ? 'weak' : fuerza === 2 ? 'medium' : 'strong') : ''"></div>
                                <div class="rg-strength-bar" :class="fuerza >= 2 ? (fuerza === 2 ? 'medium' : 'strong') : ''"></div>
                                <div class="rg-strength-bar" :class="fuerza >= 3 ? 'strong' : ''"></div>
                            </div>
                            <span class="rg-strength-label"
                                  :class="fuerza === 1 ? 'weak' : fuerza === 2 ? 'medium' : 'strong'"
                                  x-text="fuerza === 1 ? 'Débil' : fuerza === 2 ? 'Media' : 'Segura'"></span>
                        </div>
                        <p class="rg-field-hint">Mín. 8 caracteres, una mayúscula y un carácter especial (@#$!...)</p>
                        @error('password')<p class="rg-field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="rg-field-wrap" x-data="{ ver: false }">
                        <label class="rg-label">Confirmar contraseña <span class="rg-required">*</span></label>
                        <div class="rg-input-eye">
                            <input wire:model="passwordConfirm" :type="ver ? 'text' : 'password'"
                                   placeholder="Repite la contraseña" autocomplete="new-password" class="rg-field">
                            <button type="button" @click="ver = !ver" class="rg-eye-btn" tabindex="-1">
                                <svg x-show="!ver" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg x-show="ver"  width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                        @error('passwordConfirm')<p class="rg-field-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="rg-actions" style="border-top:0;margin-top:22px;padding-top:0;justify-content:flex-end">
                    <button type="submit" wire:loading.attr="disabled" class="rg-btn rg-btn-primary">
                        <span wire:loading.remove wire:target="siguientePaso">Continuar →</span>
                        <span wire:loading wire:target="siguientePaso">Verificando...</span>
                    </button>
                </div>
                </form>
                @endif
                @endif

                {{-- PASO 2: Plan --}}
                @if($paso === 2)
                @php
                    $tieneOpcionAnual = $planes->contains(fn($p) => $p->precio_anual !== null);
                    $maxDesc = $tieneOpcionAnual
                        ? $planes->filter(fn($p) => $p->precio_anual && $p->precio > 0)
                            ->max(fn($p) => round((($p->precio * 12) - $p->precio_anual) / ($p->precio * 12) * 100))
                        : 0;
                    $planesData = $planes->map(fn($p) => [
                        'id'   => $p->id,
                        'pm'   => (float) $p->precio,
                        'pa'   => $p->precio_anual ? (float) $p->precio_anual : null,
                        'dias' => (int) $p->dias_prueba_gratuita,
                    ])->keyBy('id');
                @endphp

                <div x-data="{
                    ciclo: '{{ $ciclo }}',
                    planes: @js($planesData),
                    precioMonto(id) {
                        const p = this.planes[id];
                        if (p.dias > 0) return 'S/ 0';
                        const anual = this.ciclo === 'anual' && p.pa;
                        return 'S/ ' + (anual ? Math.round(p.pa / 12) : Math.round(p.pm));
                    },
                    periodo(id) {
                        const p = this.planes[id];
                        if (p.dias > 0) return 'prueba';
                        return (this.ciclo === 'anual' && p.pa) ? '/mes anual' : '/mes';
                    },
                    nota(id) {
                        const p = this.planes[id];
                        const anual = this.ciclo === 'anual' && p.pa;
                        if (p.dias > 0 && p.pm > 0) return 'Luego S/ ' + Math.round(p.pm) + '/mes';
                        if (anual) {
                            const desc = p.pm > 0 ? Math.round(((p.pm * 12) - p.pa) / (p.pm * 12) * 100) : 0;
                            return 'Total S/ ' + Math.round(p.pa) + '/año' + (desc > 0 ? ' · ahorra ' + desc + '%' : '');
                        }
                        return '';
                    }
                }">

                <span class="rg-eyebrow">Paso 2 de 3</span>
                <h2 class="rg-card-title">Elige el plan ideal</h2>
                <p class="rg-card-sub">Puedes cambiarlo en cualquier momento. Selecciona el que mejor se adapte a tu negocio.</p>

                @error('planId')<p class="rg-field-error" style="margin-bottom:10px">{{ $message }}</p>@enderror

                {{-- Banner prueba gratuita --}}
                @if($planes->contains(fn($p) => $p->dias_prueba_gratuita > 0))
                @php $maxDias = $planes->max('dias_prueba_gratuita'); @endphp
                <div class="rg-trial-banner">
                    <div class="rg-trial-icon">★</div>
                    <div>
                        <strong>{{ $maxDias }} días gratis para usuarios nuevos</strong>
                        <span>Empieza sin costo. Al terminar la prueba elige continuar con tu plan.</span>
                    </div>
                </div>
                @endif

                {{-- Toggle facturación --}}
                @if($tieneOpcionAnual)
                <div class="rg-billing-wrap">
                    <div class="rg-billing-text">
                        <strong>¿Cómo quieres pagar?</strong>
                        <span>Con el plan anual pagas menos por mes.</span>
                    </div>
                    <div class="rg-billing-toggle">
                        <button type="button" @click="ciclo = 'mensual'"
                                :class="{ active: ciclo === 'mensual' }" class="rg-billing-btn">Mensual</button>
                        <button type="button" @click="ciclo = 'anual'"
                                :class="{ active: ciclo === 'anual' }" class="rg-billing-btn">
                            Anual
                            @if(($maxDesc ?? 0) > 0)
                                <span class="rg-save-pill">-{{ $maxDesc }}%</span>
                            @endif
                        </button>
                    </div>
                </div>
                @endif

                {{-- Plan cards --}}
                <div class="rg-plans">
                    @foreach($planes as $plan)
                    @php
                        $features = collect([
                            $plan->maximo_usuarios > 1 ? "Hasta {$plan->maximo_usuarios} usuarios" : '1 usuario',
                            $plan->maximo_locales  > 1 ? "Hasta {$plan->maximo_locales} locales"   : '1 local',
                            $plan->tiene_variantes         ? 'Variantes de productos'  : null,
                            $plan->tiene_catalogo_web      ? 'Catálogo web'             : null,
                            $plan->facturacion_electronica ? 'Facturación electrónica'  : null,
                            $plan->tiene_impresion_directa ? 'Impresión directa'        : null,
                            $plan->tiene_lista_precios     ? 'Listas de precios'        : null,
                            $plan->tiene_cuentas           ? 'Cuentas por cobrar/pagar' : null,
                        ])->filter();
                    @endphp
                    <button type="button" wire:key="plan-{{ $plan->id }}"
                            wire:click="$set('planId', {{ $plan->id }})"
                            class="rg-plan {{ $planId === $plan->id ? 'selected' : '' }}">
                        <div class="rg-plan-check">✓</div>

                        <div class="rg-plan-name">
                            {{ $plan->nombre }}
                            @if($plan->dias_prueba_gratuita > 0)
                                <span class="rg-trial-pill">★ {{ $plan->dias_prueba_gratuita }}d gratis</span>
                            @endif
                        </div>

                        <div class="rg-price">
                            <span class="rg-price-amount" x-text="precioMonto({{ $plan->id }})">
                                S/ {{ $plan->dias_prueba_gratuita > 0 ? '0' : number_format((float)$plan->precio, 0) }}
                            </span>
                            <span class="rg-price-period" x-text="periodo({{ $plan->id }})">
                                {{ $plan->dias_prueba_gratuita > 0 ? 'prueba' : '/mes' }}
                            </span>
                        </div>

                        <div class="rg-price-note" x-text="nota({{ $plan->id }})">
                            @if($plan->dias_prueba_gratuita > 0 && $plan->precio > 0)
                                Luego S/ {{ number_format((float)$plan->precio, 0) }}/mes
                            @endif
                        </div>

                        @if($features->isNotEmpty())
                        <ul class="rg-plan-features">
                            @foreach($features as $f)
                                <li>{{ $f }}</li>
                            @endforeach
                        </ul>
                        @endif
                    </button>
                    @endforeach
                </div>

                <div class="rg-actions">
                    <button type="button" wire:click="$set('paso', 1)" class="rg-btn rg-btn-secondary">← Atrás</button>
                    <button type="button" @click="$wire.avanzarDesde2(ciclo)"
                            wire:loading.attr="disabled" class="rg-btn rg-btn-primary">
                        <span wire:loading.remove wire:target="avanzarDesde2">Continuar →</span>
                        <span wire:loading wire:target="avanzarDesde2">Un momento...</span>
                    </button>
                </div>

                </div>{{-- /x-data --}}
                @endif

                {{-- PASO 3: Empresa --}}
                @if($paso === 3)
                <form wire:submit="siguientePaso">
                @php
                    $planSel    = $planes->firstWhere('id', $planId);
                    $esTrialSel = ($planSel?->dias_prueba_gratuita ?? 0) > 0;
                    $anualSel   = $ciclo === 'anual' && $planSel?->precio_anual !== null;
                    $montoSel   = $anualSel ? $planSel->precio_anual : $planSel?->precio;
                @endphp
                <span class="rg-eyebrow">Paso 3 de 3</span>
                <h2 class="rg-card-title">Registra tu empresa</h2>
                <p class="rg-card-sub">Estos datos personalizan tu espacio de trabajo y aparecerán en tus comprobantes.</p>

                <div class="rg-grid">
                    <div class="rg-field-wrap rg-col2">
                        <label class="rg-label">Nombre / Razón social <span class="rg-required">*</span></label>
                        <input wire:model="empresaNombre" type="text" placeholder="Mi Empresa S.A.C." class="rg-field">
                        @error('empresaNombre')<p class="rg-field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="rg-field-wrap">
                        <label class="rg-label">Rubro <span class="rg-label-opt">(opcional)</span></label>
                        <div class="rg-select-wrap">
                            <select wire:model="empresaRubro" class="rg-field">
                                <option value="">Selecciona un rubro</option>
                                <option>Bodega / Minimarket</option>
                                <option>Cafetería</option>
                                <option>Restaurante</option>
                                <option>Tienda de ropa</option>
                                <option>Tienda de calzado</option>
                                <option>Ferretería</option>
                                <option>Farmacia / Botica</option>
                                <option>Librería / Papelería</option>
                                <option>Electrónica y tecnología</option>
                                <option>Servicios</option>
                                <option>Otro</option>
                            </select>
                        </div>
                    </div>

                    {{-- RUC con toggle --}}
                    <div class="rg-field-wrap" x-data="{ tieneRuc: false }">
                        <label class="rg-label">RUC</label>
                        <div class="rg-ruc-toggle">
                            <div class="rg-ruc-toggle-track" :class="tieneRuc ? 'on' : ''" @click="tieneRuc = !tieneRuc"></div>
                            <span class="rg-ruc-toggle-label" @click="tieneRuc = !tieneRuc"
                                  x-text="tieneRuc ? 'Tengo RUC' : 'Sin RUC (se auto-completa)'"></span>
                        </div>
                        <div x-show="tieneRuc" x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 -translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0">
                            <input wire:model="empresaRuc" type="text" maxlength="11" placeholder="20XXXXXXXXX" class="rg-field">
                            @error('empresaRuc')<p class="rg-field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="rg-field-wrap">
                        <label class="rg-label">Correo <span class="rg-label-opt">(opcional)</span></label>
                        <input wire:model="empresaEmail" type="email" placeholder="contacto@empresa.com" class="rg-field">
                        @error('empresaEmail')<p class="rg-field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="rg-field-wrap">
                        <label class="rg-label">Teléfono <span class="rg-label-opt">(opcional)</span></label>
                        <input wire:model="empresaTel" type="text" placeholder="01 234 5678" class="rg-field">
                    </div>

                    <div class="rg-field-wrap rg-col2">
                        <label class="rg-label">Dirección <span class="rg-label-opt">(opcional)</span></label>
                        <input wire:model="empresaDireccion" type="text" placeholder="Av. Principal 123, Lima" class="rg-field">
                    </div>
                </div>

                {{-- Sección colapsable: más datos --}}
                <div x-data="{ abierto: false }" style="margin-top:18px">
                    <button type="button" @click="abierto = !abierto"
                            class="rg-collapse-trigger" :class="{ open: abierto }">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                            <path x-bind:d="abierto ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7'"/>
                        </svg>
                        <span x-text="abierto ? 'Ocultar datos adicionales' : '+ Agregar ubicación y logo (opcional)'"></span>
                    </button>

                    <div x-show="abierto" x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-2"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="rg-collapse-body">
                        <div class="rg-grid">
                            <div class="rg-field-wrap">
                                <label class="rg-label">Departamento</label>
                                <input wire:model="empresaDepartamento" type="text" placeholder="Ej. Lima" class="rg-field">
                            </div>

                            <div class="rg-field-wrap">
                                <label class="rg-label">Provincia</label>
                                <input wire:model="empresaProvincia" type="text" placeholder="Ej. Lima" class="rg-field">
                            </div>

                            <div class="rg-field-wrap">
                                <label class="rg-label">Distrito</label>
                                <input wire:model="empresaDistrito" type="text" placeholder="Ej. Miraflores" class="rg-field">
                            </div>

                            <div class="rg-field-wrap" x-data="{ nombreArchivo: null }">
                                <label class="rg-label">Logo <span class="rg-label-opt">(imagen, máx. 2 MB)</span></label>
                                <label class="rg-file-btn">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
                                    </svg>
                                    <span x-text="nombreArchivo ?? 'Subir logo'"></span>
                                    <input wire:model="empresaLogo" type="file" accept="image/*"
                                           @change="nombreArchivo = $event.target.files[0]?.name ?? null">
                                </label>
                                @error('empresaLogo')<p class="rg-field-error">{{ $message }}</p>@enderror
                                @if($empresaLogo)
                                <div class="rg-logo-preview">
                                    <img src="{{ $empresaLogo->temporaryUrl() }}" alt="Preview">
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Resumen --}}
                @if($planSel)
                <div class="rg-summary">
                    <div class="rg-summary-row">
                        <span>Plan seleccionado</span>
                        <strong>{{ $planSel->nombre }}</strong>
                    </div>
                    <div class="rg-summary-row">
                        <span>Facturación</span>
                        <strong>{{ $anualSel
                            ? 'Anual · S/ ' . number_format((float)$planSel->precio_anual, 0) . '/año'
                            : 'Mensual · S/ ' . number_format((float)$planSel->precio, 0) . '/mes' }}</strong>
                    </div>
                    @if($esTrialSel)
                    <div class="rg-summary-row">
                        <span>Periodo gratuito</span>
                        <span class="rg-summary-free">{{ $planSel->dias_prueba_gratuita }} días gratis</span>
                    </div>
                    <div class="rg-summary-row">
                        <span>Primer cobro</span>
                        <strong>
                            S/ {{ number_format((float)($anualSel ? $planSel->precio_anual : $planSel->precio), 0) }}
                            {{ $anualSel ? '/año' : '/mes' }} · después de los {{ $planSel->dias_prueba_gratuita }} días
                        </strong>
                    </div>
                    @else
                    <div class="rg-summary-row">
                        <span>Monto</span>
                        <strong>S/ {{ number_format((float)$montoSel, 0) }} {{ $anualSel ? '/año' : '/mes' }}</strong>
                    </div>
                    @endif
                </div>
                @endif

                {{-- Términos y condiciones --}}
                <div class="rg-terms-wrap">
                    <label class="rg-terms-label">
                        <input wire:model="aceptaTerminos" type="checkbox" class="rg-terms-check">
                        <span>He leído y acepto los <a href="{{ route('terminos') }}" target="_blank" class="rg-terms-link">Términos y Condiciones</a>.</span>
                    </label>
                    @error('aceptaTerminos')<p class="rg-field-error" style="margin-top:6px">{{ $message }}</p>@enderror
                </div>

                <div class="rg-actions" style="border-top:0;padding-top:0">
                    <button type="button" wire:click="$set('paso', 2)" class="rg-btn rg-btn-secondary">← Atrás</button>
                    <button type="submit" wire:loading.attr="disabled" class="rg-btn rg-btn-primary rg-btn-final">
                        <span wire:loading.remove wire:target="siguientePaso">Crear mi cuenta ✓</span>
                        <span wire:loading wire:target="siguientePaso">Validando...</span>
                    </button>
                </div>
                </form>
                @endif

                {{-- PASO 4: Estado --}}
                @if($paso === 4)
                <div x-data x-init="$wire.finalizarRegistro()">

                    @if($estadoCreacion === 'pendiente' || $estadoCreacion === 'creando')
                    <div class="rg-state">
                        <div class="rg-state-icon loading">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" class="animate-spin">
                                <circle cx="12" cy="12" r="10" stroke="#1E5AA8" stroke-width="3" stroke-opacity=".25"/>
                                <path d="M4 12a8 8 0 018-8" stroke="#1E5AA8" stroke-width="3" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <h2>Construyendo tu empresa…</h2>
                        <p>Estamos configurando todo. Solo tomará unos segundos.</p>
                    </div>
                    @endif

                    @if($estadoCreacion === 'listo')
                    <div class="rg-state">
                        <div class="rg-state-icon success">
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="10" fill="#17A673" fill-opacity=".15"/>
                                <path d="M7 12l3.5 3.5L17 8.5" stroke="#17A673" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <h2>¡Todo listo!</h2>
                        <p>Tu empresa fue creada exitosamente. Redirigiendo al sistema…</p>
                        <div x-init="setTimeout(() => window.location.href = '{{ $redirectUrl }}', 1500)"></div>
                    </div>
                    @endif

                    @if($estadoCreacion === 'error')
                    <div class="rg-state">
                        <div class="rg-state-icon error">
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="10" fill="#D94848" fill-opacity=".12"/>
                                <path d="M12 8v4m0 4h.01" stroke="#D94848" stroke-width="2.5" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <h2>Algo salió mal</h2>
                        <p>{{ $mensajeError }}</p>
                        <button wire:click="reintentar" class="rg-btn rg-btn-primary">Volver a intentar</button>
                    </div>
                    @endif

                </div>
                @endif

            </div>{{-- /rg-card --}}


        </div>{{-- /rg-shell --}}
    </main>

</div>{{-- /rg-page --}}

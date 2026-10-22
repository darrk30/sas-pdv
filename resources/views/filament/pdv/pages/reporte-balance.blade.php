<x-filament-panels::page>

    {{ $this->form }}

    @php
        $r            = $this->getResumenPeriodo();
        $saldos       = $this->getSaldosPorMetodo();
        $ventasMetodo    = $this->getVentasPorMetodoPeriodo();
        $gastosMetodo    = $this->getGastosPorMetodoPeriodo();
        $ingresosMetodo  = $this->getIngresosPorMetodoPeriodo();
        $egresosMetodo   = $this->getEgresosPorMetodoPeriodo();

        $empresa        = \Filament\Facades\Filament::getTenant();
        $tieneCuentas   = $empresa && $empresa->tieneFeature('cuentas');
        $totalIngresos  = $r['montoVentas'] + $r['ingresosCaja'];
        $totalEgresos   = $r['totalGastos'];
        $balancePeriodo = $totalIngresos - $totalEgresos;
        $gananciaBruta  = $r['gananciaBruta'];
        $porCobrar      = $tieneCuentas ? ($r['porCobrar'] ?? 0) : 0;

        $fmt = fn($v) => 'S/ ' . number_format((float) $v, 2);
        $gastosIdx = collect($gastosMetodo)->keyBy('nombre');

        // Indexar ventasMetodo por nombre para acceso rápido
        $ventasIdx = collect($ventasMetodo)->keyBy('nombre');

        $diasLabel = $r['desde'] === $r['hasta']
            ? \Carbon\Carbon::parse($r['desde'])->translatedFormat('d \d\e F Y')
            : \Carbon\Carbon::parse($r['desde'])->format('d/m/Y') . ' — ' . \Carbon\Carbon::parse($r['hasta'])->format('d/m/Y');
    @endphp

    <div class="mt-2 space-y-3">

        <p class="text-xs font-medium text-gray-400 uppercase tracking-widest">{{ $diasLabel }}</p>

        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- CARD 1 — Ingresos / Egresos / Balance                            --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        @php $balPos = $balancePeriodo >= 0; @endphp
        <div x-data="{ open: false }"
             class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">

            <button @click="open = !open"
                    class="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                <div class="flex items-center gap-4 min-w-0">
                    <div class="shrink-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Balance del período</p>
                        <p class="text-2xl font-bold tabular-nums {{ $balPos ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                            {{ ($balPos ? '+' : '') . $fmt($balancePeriodo) }}
                        </p>
                        @if($porCobrar > 0)
                        <p class="text-xs font-medium text-warning-600 dark:text-warning-400 mt-0.5">
                            + {{ $fmt($porCobrar) }} por cobrar (crédito)
                        </p>
                        @endif
                    </div>
                    <div class="hidden md:flex gap-4 ml-4">
                        <div>
                            <p class="text-xs text-success-600 dark:text-success-400 font-medium">Ingresos</p>
                            <p class="text-base font-semibold tabular-nums text-gray-700 dark:text-gray-200">{{ $fmt($totalIngresos) }}</p>
                        </div>
                        <div class="w-px bg-gray-100 dark:bg-gray-700"></div>
                        <div>
                            <p class="text-xs text-danger-600 dark:text-danger-400 font-medium">Egresos</p>
                            <p class="text-base font-semibold tabular-nums text-gray-700 dark:text-gray-200">-{{ $fmt($totalEgresos) }}</p>
                        </div>
                    </div>
                </div>
                <x-heroicon-o-chevron-down class="w-5 h-5 text-gray-400 transition-transform duration-200 shrink-0" x-bind:class="open ? 'rotate-180' : ''" />
            </button>

            <div x-show="open" x-collapse class="border-t border-gray-100 dark:border-gray-700">
                <div class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-gray-100 dark:divide-gray-700">
                    <div class="p-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-success-600 dark:text-success-400 mb-3">Ingresos</p>
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500 dark:text-gray-400">Ventas cobradas ({{ $r['totalVentas'] }})</span>
                                <span class="tabular-nums font-medium text-gray-800 dark:text-gray-200">{{ $fmt($r['montoVentas']) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500 dark:text-gray-400">Ingresos de caja</span>
                                <span class="tabular-nums font-medium text-gray-800 dark:text-gray-200">{{ $fmt($r['ingresosCaja']) }}</span>
                            </div>
                            @if($porCobrar > 0)
                            <div class="flex justify-between text-sm border-t border-dashed border-warning-200 dark:border-warning-700 pt-2">
                                <span class="text-warning-600 dark:text-warning-400 flex items-center gap-1">
                                    <x-heroicon-o-clock class="w-3.5 h-3.5" />
                                    Por cobrar (crédito)
                                </span>
                                <span class="tabular-nums font-medium text-warning-600 dark:text-warning-400">{{ $fmt($porCobrar) }}</span>
                            </div>
                            @endif
                            <div class="flex justify-between text-sm font-bold border-t border-gray-100 dark:border-gray-700 pt-2 mt-1 text-success-600 dark:text-success-400">
                                <span>Total cobrado</span>
                                <span class="tabular-nums">{{ $fmt($totalIngresos) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="p-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-danger-600 dark:text-danger-400 mb-3">Egresos</p>
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500 dark:text-gray-400">Gastos variables</span>
                                <span class="tabular-nums font-medium text-gray-800 dark:text-gray-200">-{{ $fmt($r['gastosVariables']) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500 dark:text-gray-400">Compras</span>
                                <span class="tabular-nums font-medium text-gray-800 dark:text-gray-200">-{{ $fmt($r['compras']) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500 dark:text-gray-400">Egresos de caja</span>
                                <span class="tabular-nums font-medium text-gray-800 dark:text-gray-200">-{{ $fmt($r['egresosCaja']) }}</span>
                            </div>
                            <div class="flex justify-between text-sm font-bold border-t border-gray-100 dark:border-gray-700 pt-2 mt-1 text-danger-600 dark:text-danger-400">
                                <span>Total</span>
                                <span class="tabular-nums">-{{ $fmt($totalEgresos) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- CARD 2 — Por método de pago                                      --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        <div x-data="{ open: false }"
             class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">

            <button @click="open = !open"
                    class="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Por método de pago</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Saldo acumulado histórico</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="hidden md:flex flex-wrap gap-2">
                        @foreach($saldos as $s)
                        @php
                            $vm = $ventasIdx->get($s['nombre']);
                            $esC = $vm['es_credito'] ?? false;
                            if ($esC && !$tieneCuentas) continue;
                            $pillVal = $esC ? ($vm['total'] ?? 0) : $s['saldo'];
                            $pillPos = $pillVal >= 0;
                        @endphp
                        <span class="rounded-full px-3 py-1 text-xs font-semibold
                            {{ $esC ? 'bg-warning-100 text-warning-700 dark:bg-warning-900 dark:text-warning-300' : ($pillPos ? 'bg-success-100 text-success-700 dark:bg-success-900 dark:text-success-300' : 'bg-danger-100 text-danger-700 dark:bg-danger-900 dark:text-danger-300') }}">
                            {{ $s['nombre'] }}: {{ $fmt($pillVal) }}{{ $esC ? ' ⏳' : '' }}
                        </span>
                        @endforeach
                    </div>
                    <x-heroicon-o-chevron-down class="w-5 h-5 text-gray-400 transition-transform duration-200 shrink-0" x-bind:class="open ? 'rotate-180' : ''" />
                </div>
            </button>

            <div x-show="open" x-collapse class="border-t border-gray-100 dark:border-gray-700 p-4">
                <div class="space-y-2">
                    @forelse($saldos as $s)
                    @php
                        $ventaM    = $ventasIdx->get($s['nombre']);
                        $esCredito = $ventaM['es_credito'] ?? false;
                        if ($esCredito && !$tieneCuentas) continue;
                        $gasto     = $gastosIdx->get($s['nombre']);
                        // Crédito: muestra el por cobrar; contado: muestra el saldo real
                        $balM      = $esCredito ? ($ventaM['total'] ?? 0) : $s['saldo'];
                        $sp        = $balM >= 0;
                    @endphp
                    <div x-data="{ openM: false }"
                         class="rounded-xl border {{ $esCredito ? 'border-warning-200 dark:border-warning-700' : ($sp ? 'border-gray-200 dark:border-gray-600' : 'border-danger-200 dark:border-danger-700') }} overflow-hidden">

                        <button @click="openM = !openM"
                                class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $s['nombre'] }}</p>
                                @if($esCredito)
                                <span class="text-xs px-1.5 py-0.5 rounded bg-warning-100 dark:bg-warning-900 text-warning-700 dark:text-warning-300 font-medium">Crédito</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-3">
                                <p class="text-base font-bold tabular-nums {{ $esCredito ? 'text-warning-600 dark:text-warning-400' : ($sp ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400') }}">
                                    {{ $fmt($balM) }}
                                </p>
                                <x-heroicon-o-chevron-down class="w-4 h-4 text-gray-400 transition-transform duration-200 shrink-0" x-bind:class="openM ? 'rotate-180' : ''" />
                            </div>
                        </button>

                        <div x-show="openM" x-collapse
                             class="border-t {{ $esCredito ? 'border-warning-100 dark:border-warning-800' : ($sp ? 'border-gray-100 dark:border-gray-700' : 'border-danger-100 dark:border-danger-800') }} px-4 py-3">
                            @php
                                $ingreso = $ingresosMetodo[$s['nombre']] ?? null;
                                $egreso  = $egresosMetodo[$s['nombre']] ?? null;
                            @endphp
                            <div class="space-y-2">
                                @if($esCredito)
                                {{-- Método crédito: mostrar como por cobrar, no suma al balance --}}
                                @if($ventaM)
                                <div class="flex justify-between text-sm">
                                    <span class="text-warning-600 dark:text-warning-400 flex items-center gap-1">
                                        <x-heroicon-o-clock class="w-3.5 h-3.5" />
                                        Por cobrar (período)
                                    </span>
                                    <span class="tabular-nums font-medium text-warning-600 dark:text-warning-400">{{ $fmt($ventaM['total']) }}</span>
                                </div>
                                @endif
                                <p class="text-xs text-gray-400 dark:text-gray-500 italic">Las ventas a crédito no se suman al balance. Se registran como ingreso cuando el cliente paga.</p>
                                @else
                                {{-- Método contado normal: siempre mostrar ventas --}}
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Ventas</span>
                                    <span class="tabular-nums font-medium text-success-600 dark:text-success-400">{{ $fmt($ventaM['total'] ?? 0) }}</span>
                                </div>
                                @endif
                                @if($ingreso)
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Ingresos de caja</span>
                                    <span class="tabular-nums font-medium text-success-600 dark:text-success-400">{{ $fmt($ingreso['total']) }}</span>
                                </div>
                                @endif
                                @if($egreso)
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Egresos de caja</span>
                                    <span class="tabular-nums font-medium text-danger-600 dark:text-danger-400">-{{ $fmt($egreso['total']) }}</span>
                                </div>
                                @endif
                                @if($gasto)
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Gastos</span>
                                    <span class="tabular-nums font-medium text-danger-600 dark:text-danger-400">-{{ $fmt($gasto['total']) }}</span>
                                </div>
                                @endif
                                <div class="flex justify-between text-sm font-bold border-t border-gray-100 dark:border-gray-700 pt-2 mt-1
                                    {{ $esCredito ? 'text-warning-600 dark:text-warning-400' : ($sp ? 'text-success-700 dark:text-success-300' : 'text-danger-600 dark:text-danger-400') }}">
                                    <span>{{ $esCredito ? 'Por cobrar' : 'Balance total registrado' }}</span>
                                    <span class="tabular-nums">{{ $fmt($balM) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    @empty
                    <p class="text-sm text-gray-400 py-2">Sin métodos de pago.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- CARD 3 — Ganancias                                               --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        @php $brutaPos = $gananciaBruta >= 0; @endphp
        <div x-data="{ open: false }"
             class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">

            <button @click="open = !open"
                    class="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Ganancia Estimada</p>
                    <p class="text-2xl font-bold tabular-nums mt-0.5 {{ $brutaPos ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                        {{ ($brutaPos ? '+' : '') . $fmt($gananciaBruta) }}
                    </p>
                </div>
                <x-heroicon-o-chevron-down class="w-5 h-5 text-gray-400 transition-transform duration-200 shrink-0" x-bind:class="open ? 'rotate-180' : ''" />
            </button>

            <div x-show="open" x-collapse class="border-t border-gray-100 dark:border-gray-700 px-5 py-4">
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 dark:text-gray-400">Ventas cobradas</span>
                        <span class="tabular-nums font-medium text-gray-700 dark:text-gray-200">{{ $fmt($r['montoVentas']) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 dark:text-gray-400">Costo de ventas</span>
                        <span class="tabular-nums font-medium text-gray-700 dark:text-gray-200">-{{ $fmt($r['costoVentas']) }}</span>
                    </div>
                    <div class="flex justify-between text-sm font-bold border-t border-gray-100 dark:border-gray-700 pt-2 mt-1 {{ $brutaPos ? 'text-success-700 dark:text-success-300' : 'text-danger-600 dark:text-danger-400' }}">
                        <span>Ganancia</span>
                        <span class="tabular-nums">{{ ($brutaPos ? '+' : '') . $fmt($gananciaBruta) }}</span>
                    </div>
                </div>
            </div>
        </div>

    </div>

</x-filament-panels::page>

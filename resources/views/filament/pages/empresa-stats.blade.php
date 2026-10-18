<x-filament-panels::page>
<div class="space-y-6">

    {{-- ── Info de suscripción ──────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">

        {{-- Plan --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Plan</p>
            <p class="mt-1 text-lg font-bold text-gray-900 dark:text-white">
                {{ $sus?->plan?->nombre ?? '—' }}
            </p>
            @if($sus?->es_prueba_gratuita)
                <span class="mt-1 inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-300">
                    Trial gratuito
                </span>
            @endif
        </div>

        {{-- Estado suscripción --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Suscripción</p>
            @if($sus?->fecha_fin)
                <p class="mt-1 text-lg font-bold {{ $diasRestantes !== null && $diasRestantes <= 7 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">
                    Vence {{ $sus->fecha_fin->format('d/m/Y') }}
                </p>
                <p class="text-sm {{ $diasRestantes !== null && $diasRestantes <= 7 ? 'text-red-500' : 'text-gray-500 dark:text-gray-400' }}">
                    @if($diasRestantes !== null)
                        @if($diasRestantes === 0)
                            Vence hoy
                        @elseif($diasRestantes < 0)
                            Venció hace {{ abs($diasRestantes) }} días
                        @else
                            {{ $diasRestantes }} días restantes
                        @endif
                    @endif
                </p>
            @else
                <p class="mt-1 text-lg font-bold text-gray-400">Sin fecha</p>
            @endif
        </div>

        {{-- Módulos activos --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm sm:col-span-2">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Módulos activos</p>
            <div class="mt-2 flex flex-wrap gap-1.5">
                @forelse($modulosActivos as $mod)
                    <span class="inline-flex items-center rounded-full bg-primary-100 dark:bg-primary-900 px-2.5 py-0.5 text-xs font-medium text-primary-700 dark:text-primary-300">
                        {{ ucfirst($mod) }}
                    </span>
                @empty
                    <span class="text-sm text-gray-400">Ninguno adicional</span>
                @endforelse
            </div>
        </div>

    </div>

    {{-- ── Tarjetas de stats ────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">

        @php
            $cards = [
                ['label' => 'Productos',      'value' => number_format($totalProductos), 'icon' => 'heroicon-o-cube',          'color' => 'blue'],
                ['label' => 'Clientes',        'value' => number_format($totalClientes),  'icon' => 'heroicon-o-users',          'color' => 'indigo'],
                ['label' => 'Usuarios',        'value' => number_format($totalUsuarios),  'icon' => 'heroicon-o-user-circle',    'color' => 'violet'],
                ['label' => 'Ventas hoy',      'value' => number_format($ventasHoy->n),   'icon' => 'heroicon-o-shopping-cart',  'color' => 'emerald'],
                ['label' => 'Ventas mes',      'value' => number_format($ventasMes->n),   'icon' => 'heroicon-o-shopping-bag',   'color' => 'teal'],
                ['label' => 'Ticket promedio', 'value' => 'S/ ' . number_format($ticketPromedio, 2), 'icon' => 'heroicon-o-receipt-percent', 'color' => 'amber'],
            ];
            $colorMap = [
                'blue'    => 'bg-blue-50 dark:bg-blue-950 border-blue-200 dark:border-blue-800 text-blue-700 dark:text-blue-300',
                'indigo'  => 'bg-indigo-50 dark:bg-indigo-950 border-indigo-200 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300',
                'violet'  => 'bg-violet-50 dark:bg-violet-950 border-violet-200 dark:border-violet-800 text-violet-700 dark:text-violet-300',
                'emerald' => 'bg-emerald-50 dark:bg-emerald-950 border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300',
                'teal'    => 'bg-teal-50 dark:bg-teal-950 border-teal-200 dark:border-teal-800 text-teal-700 dark:text-teal-300',
                'amber'   => 'bg-amber-50 dark:bg-amber-950 border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-300',
            ];
        @endphp

        @foreach($cards as $card)
        <div class="rounded-xl border {{ $colorMap[$card['color']] }} p-4 shadow-sm">
            <div class="flex items-center gap-2 mb-1">
                <x-filament::icon :icon="$card['icon']" class="h-4 w-4 opacity-70" />
                <p class="text-xs font-semibold uppercase tracking-widest opacity-70">{{ $card['label'] }}</p>
            </div>
            <p class="text-2xl font-bold">{{ $card['value'] }}</p>
        </div>
        @endforeach

    </div>

    {{-- ── Monto facturado ──────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">

        @php
            $montos = [
                ['label' => 'Hoy',           'value' => 'S/ ' . number_format($ventasHoy->monto,  2)],
                ['label' => now()->translatedFormat('F Y'), 'value' => 'S/ ' . number_format($ventasMes->monto,  2)],
                ['label' => 'Año ' . now()->year, 'value' => 'S/ ' . number_format($ventasAnio->monto, 2)],
            ];
        @endphp

        @foreach($montos as $m)
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm text-center">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Facturado — {{ $m['label'] }}</p>
            <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ $m['value'] }}</p>
        </div>
        @endforeach

    </div>

    {{-- ── Últimas ventas ───────────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Últimas 10 ventas</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">#</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Modo</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Comprobante</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Total</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Fecha</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @php
                        $modoColor = [
                            'pdv'         => 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300',
                            'web'         => 'bg-violet-100 dark:bg-violet-900 text-violet-700 dark:text-violet-300',
                            'restaurante' => 'bg-orange-100 dark:bg-orange-900 text-orange-700 dark:text-orange-300',
                        ];
                    @endphp
                    @forelse($ultimasVentas as $venta)
                    @php $modo = strtolower($venta->tipo ?? ''); @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-4 py-3 font-mono text-gray-500 dark:text-gray-400 text-xs">{{ $venta->id }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $modoColor[$modo] ?? 'bg-gray-100 text-gray-600' }}">
                                {{ strtoupper($modo ?: '—') }}
                            </span>
                        </td>
                        <td class="px-4 py-3 font-mono text-gray-900 dark:text-white text-sm">
                            {{ $venta->serie_codigo ? $venta->serie_codigo . '-' . $venta->correlativo : ($venta->correlativo ?? '—') }}
                        </td>
                        <td class="px-4 py-3 text-right font-semibold text-emerald-600 dark:text-emerald-400">S/ {{ number_format($venta->total, 2) }}</td>
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs">{{ $venta->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-400">Sin ventas registradas</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
</x-filament-panels::page>

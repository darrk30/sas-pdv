<div>
    {{-- ── ESTADO: Confirmación ──────────────────────────────────────────── --}}
    @if ($estado === 'confirmando')
        <div class="p-6 space-y-5">
            <div class="flex items-start gap-3 text-danger-600 dark:text-danger-400">
                <x-heroicon-o-exclamation-triangle class="w-8 h-8 shrink-0 mt-0.5" />
                <div>
                    <p class="font-semibold text-base">¿Eliminar empresa permanentemente?</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                        Se eliminará <strong>toda</strong> la data: productos, ventas, órdenes,
                        inventario, roles, usuarios vinculados y configuración.
                        Esta acción no se puede deshacer.
                    </p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Escribe <span class="font-bold text-gray-900 dark:text-white">{{ $nombreEmpresa }}</span> para confirmar
                </label>
                <input
                    type="text"
                    wire:model="confirmacion"
                    placeholder="{{ $nombreEmpresa }}"
                    autocomplete="off"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-danger-500"
                />
                @error('confirmacion')
                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Barra de progreso simulada mientras procesa --}}
            <div wire:loading wire:target="confirmar" class="space-y-3"
                x-data="{
                    step: 0,
                    steps: @js(\App\Livewire\Admin\EliminarEmpresaModal::$pasos),
                    pct: 0,
                    init() {
                        let total = this.steps.length;
                        let i = setInterval(() => {
                            if (this.step < total - 1) {
                                this.step++;
                                this.pct = Math.round((this.step / total) * 92);
                            }
                        }, 600);
                    }
                }"
            >
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    Eliminando datos de <strong class="text-gray-900 dark:text-white">{{ $nombreEmpresa }}</strong>…
                </p>
                <div>
                    <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                        <span x-text="'Eliminando ' + steps[step] + '…'">Eliminando…</span>
                        <span x-text="pct + '%'">0%</span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5 overflow-hidden">
                        <div
                            class="bg-danger-500 h-2.5 rounded-full transition-all duration-500"
                            :style="'width: ' + pct + '%'"
                        ></div>
                    </div>
                </div>
                <ul class="space-y-1.5 max-h-48 overflow-y-auto">
                    <template x-for="(paso, i) in steps" :key="i">
                        <li class="flex items-center gap-2 text-sm">
                            <template x-if="i < step">
                                <x-heroicon-s-check-circle class="w-4 h-4 text-success-500 shrink-0" />
                            </template>
                            <template x-if="i === step">
                                <svg class="w-4 h-4 text-danger-500 shrink-0 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                            </template>
                            <template x-if="i > step">
                                <x-heroicon-o-clock class="w-4 h-4 text-gray-300 dark:text-gray-600 shrink-0" />
                            </template>
                            <span
                                :class="{
                                    'text-gray-400 dark:text-gray-500 line-through': i < step,
                                    'font-medium text-gray-900 dark:text-white': i === step,
                                    'text-gray-400 dark:text-gray-500': i > step
                                }"
                                x-text="paso"
                            ></span>
                        </li>
                    </template>
                </ul>
            </div>

            <div class="flex justify-end gap-3 pt-2" wire:loading.remove wire:target="confirmar">
                <button
                    type="button"
                    wire:click="cancelar"
                    class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    wire:click="confirmar"
                    class="px-4 py-2 text-sm rounded-lg bg-danger-600 hover:bg-danger-700 text-white font-medium transition"
                >
                    Sí, eliminar empresa
                </button>
            </div>
        </div>

    {{-- ── ESTADO: Completado ────────────────────────────────────────────── --}}
    @elseif ($estado === 'completado')
        <div
            class="p-6 space-y-4 text-center"
            x-init="setTimeout(() => window.location.href = '{{ $indexUrl }}', 1500)"
        >
            <x-heroicon-o-check-circle class="w-14 h-14 text-success-500 mx-auto" />
            <p class="text-base font-semibold text-gray-900 dark:text-white">Empresa eliminada correctamente</p>
            <p class="text-sm text-gray-500 dark:text-gray-400">Redirigiendo…</p>
        </div>

    {{-- ── ESTADO: Error ─────────────────────────────────────────────────── --}}
    @elseif ($estado === 'error')
        <div class="p-6 space-y-4">
            <div class="flex items-start gap-3 text-danger-600 dark:text-danger-400">
                <x-heroicon-o-x-circle class="w-6 h-6 shrink-0 mt-0.5" />
                <div>
                    <p class="font-semibold">Ocurrió un error</p>
                    <p class="text-sm mt-1 text-gray-600 dark:text-gray-400 font-mono break-all">{{ $mensajeError }}</p>
                </div>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                No se eliminó ningún dato — la operación es atómica (todo o nada).
                Revisa el error e intenta de nuevo.
            </p>
            <div class="flex justify-end">
                <button
                    type="button"
                    wire:click="cancelar"
                    class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                >
                    Cerrar
                </button>
            </div>
        </div>
    @endif
</div>

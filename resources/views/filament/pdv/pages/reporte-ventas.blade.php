<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/ventas-sesion.css') }}?v={{ filemtime(public_path('css/ventas-sesion.css')) }}">

@php $resumen = $this->getResumen(); @endphp

@if(! empty($resumen['porMetodo']))
<div class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-gray-600 dark:text-gray-400">
    <span class="font-medium text-gray-800 dark:text-gray-200">Por método de pago:</span>
    @foreach($resumen['porMetodo'] as $m)
        <span>{{ $m['nombre'] }} <strong class="text-gray-900 dark:text-white">S/ {{ number_format($m['total'], 2) }}</strong></span>
    @endforeach
</div>
@endif

<div class="mb-3">
    {{ $this->form }}
    @if($this->hayFiltros())
        <div class="mt-3 flex justify-end">
            <x-filament::button wire:click="limpiarFiltros" color="gray" size="sm" icon="heroicon-m-x-mark" outlined>
                Limpiar filtros
            </x-filament::button>
        </div>
    @endif
</div>

{{ $this->table }}


{{-- ══ MODAL DETALLE ══ --}}
@if($ventaModalId)
    @php $vm = $this->getVentaModal(); @endphp
    @if($vm)
    <div class="vs-overlay" wire:key="rv-modal-detalle-{{ $vm->id }}">
        <div class="vs-overlay__backdrop" wire:click="cerrarDetalle"></div>
        <div class="vs-modal">

            <div class="vs-modal__header">
                <div>
                    <h3 class="vs-modal__titulo">
                        {{ ($vm->serie?->serie ?? '---') . '-' . $vm->correlativo }}
                    </h3>
                    <p class="vs-modal__subtitulo">{{ $vm->created_at->format('d/m/Y H:i') }}</p>
                </div>
                <div class="vs-modal__badges">
                    @if($vm->estado === \App\Enums\EstadoVenta::Anulada)
                        <span class="vs-badge vs-badge--anulada">Anulada</span>
                    @else
                        <span class="vs-badge vs-badge--ok">Completada</span>
                    @endif
                    @if($vm->notas && str_starts_with($vm->notas, 'Delivery'))
                        <span class="vs-badge vs-badge--despacho">Delivery</span>
                    @endif
                    @if($vm->estado_despacho === \App\Enums\EstadoVenta::PendienteEnvio)
                        <span class="vs-badge vs-badge--despacho">Pendiente de envío</span>
                    @endif
                </div>
                <button class="vs-modal__cerrar" wire:click="cerrarDetalle">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="vs-modal__cliente">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                </svg>
                <span><strong>{{ $vm->cliente_nombre }}</strong> — {{ strtoupper($vm->cliente_tipo_doc) }} {{ $vm->cliente_num_doc }}</span>
            </div>

            <div class="vs-modal__body">

                @if($vm->notas && str_starts_with($vm->notas, 'Delivery'))
                @php
                    $rvDp = [];
                    foreach (explode(' | ', $vm->notas) as $p) {
                        if (str_contains($p, ': ')) { [$k,$v] = explode(': ', $p, 2); $rvDp[trim($k)] = trim($v); }
                    }
                @endphp
                <p class="vs-modal__section-label">Datos delivery</p>
                <div class="vd-delivery-datos" style="margin-bottom:.75rem">
                    @if(isset($rvDp['Cliente']))    <span><strong>Cliente:</strong> {{ $rvDp['Cliente'] }}</span>         @endif
                    @if(isset($rvDp['Tel']))        <span><strong>Tel:</strong> {{ $rvDp['Tel'] }}</span>                 @endif
                    @if(isset($rvDp['Dir']))        <span><strong>Dir:</strong> {{ $rvDp['Dir'] }}</span>                 @endif
                    @if(isset($rvDp['Repartidor']))<span><strong>Repartidor:</strong> {{ $rvDp['Repartidor'] }}</span>   @endif
                </div>
                @endif

                @if($vm->despacho_direccion || $vm->despacho_departamento || $vm->despacho_provincia || $vm->despacho_distrito)
                <p class="vs-modal__section-label">Datos de despacho</p>
                <div class="vd-delivery-datos" style="margin-bottom:.75rem">
                    @if($vm->despacho_departamento || $vm->despacho_provincia || $vm->despacho_distrito)
                    <span><strong>Ubicación:</strong>
                        {{ implode(' / ', array_filter([$vm->despacho_departamento, $vm->despacho_provincia, $vm->despacho_distrito])) }}
                    </span>
                    @endif
                    @if($vm->despacho_direccion)
                    <span><strong>Dirección:</strong> {{ $vm->despacho_direccion }}</span>
                    @endif
                </div>
                @endif

                <p class="vs-modal__section-label">Ítems</p>
                <div class="vs-modal__table-wrap">
                    <table class="vs-table">
                        <thead>
                            <tr>
                                <th>Descripción</th>
                                <th class="vs-ta-right">Cant.</th>
                                <th class="vs-ta-right">P. Unit.</th>
                                <th class="vs-ta-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($vm->detalles as $d)
                                @php
                                    $cant    = (float) $d->cantidad;
                                    $cantFmt = $cant == floor($cant)
                                        ? number_format($cant, 0)
                                        : rtrim(rtrim(number_format($cant, 3), '0'), '.');
                                    $simbolo = $d->producto?->unidadMedida?->simbolo
                                        ?? $d->variante?->producto?->unidadMedida?->simbolo
                                        ?? null;
                                @endphp
                                <tr>
                                    <td>{{ $d->descripcion }}</td>
                                    <td class="vs-ta-right" style="white-space:nowrap">
                                        {{ $cantFmt }}
                                        @if($simbolo)
                                            <span style="font-size:.72rem;color:var(--vs-text-muted);margin-left:.15rem">{{ $simbolo }}</span>
                                        @endif
                                    </td>
                                    <td class="vs-ta-right">S/ {{ number_format($d->precio_unitario, 2) }}</td>
                                    <td class="vs-ta-right">S/ {{ number_format($d->total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="vs-modal__totales">
                    @if($vm->descuento_total > 0)
                        <div class="vs-modal__total-fila vs-modal__total-fila--descuento">
                            <span>Descuento</span>
                            <span class="vs-descuento-col">
                                <span>−S/ {{ number_format($vm->descuento_total, 2) }}</span>
                                @if($vm->cupon)
                                    <span class="vs-cupon-badge">{{ $vm->cupon->codigo }}</span>
                                @endif
                            </span>
                        </div>
                    @endif
                    @if($vm->op_gravadas > 0)
                    <div class="vs-modal__total-fila">
                        <span>Op. Gravada</span>
                        <span>S/ {{ number_format($vm->op_gravadas, 2) }}</span>
                    </div>
                    @endif
                    @if($vm->igv > 0)
                    <div class="vs-modal__total-fila">
                        <span>IGV (18%)</span>
                        <span>S/ {{ number_format($vm->igv, 2) }}</span>
                    </div>
                    @endif
                    <div class="vs-modal__total-fila vs-modal__total-fila--grande">
                        <span>Total</span>
                        <span>S/ {{ number_format($vm->total, 2) }}</span>
                    </div>
                </div>

                <p class="vs-modal__section-label" style="margin-top:1rem">Pagos</p>
                <div class="vs-modal__pagos">
                    @foreach($vm->pagos->filter(fn($p) => $p->metodoPago?->condicion_pago !== \App\Enums\CondicionPago::Credito || $vm->estado_pago === 'pendiente') as $pago)
                        <div class="vs-modal__pago-item">
                            <div class="vs-modal__pago-info">
                                <span class="vs-modal__pago-metodo">{{ $pago->metodoPago?->nombre ?? '—' }}</span>
                                @if($pago->referencia)
                                    <span class="vs-modal__pago-ref">{{ $pago->referencia }}</span>
                                @endif
                            </div>
                            <span class="vs-modal__pago-monto">S/ {{ number_format($pago->monto, 2) }}</span>
                        </div>
                    @endforeach
                    @if($vm->saldo_pendiente > 0)
                        <div class="vs-modal__pago-item vs-modal__pago-item--pendiente">
                            <span>Saldo pendiente</span>
                            <span>S/ {{ number_format($vm->saldo_pendiente, 2) }}</span>
                        </div>
                    @endif
                </div>

            </div>

            <div class="vs-modal__footer">
                <button class="vs-modal__btn-cerrar" wire:click="cerrarDetalle">Cerrar</button>
            </div>

        </div>
    </div>
    @endif
@endif


{{-- ══ MODAL ANULAR ══ --}}
@if($modalAnular)
    @php
        $va           = $this->getVentaAnular();
        $tieneStock   = $va ? $this->tieneItemsConStock() : false;
        $codAnular    = $va ? (($va->serie?->serie ?? '---') . '-' . $va->correlativo) : '';
        $necesitaBaja = $va ? $this->necesitaBajaSunat() : false;
    @endphp
    @if($va)
    <div class="vs-overlay" wire:key="rv-modal-anular-{{ $va->id }}">
        <div class="vs-overlay__backdrop" wire:click="cerrarAnular"></div>
        <div class="vs-modal vs-modal--anular">

            <div class="vs-modal__header">
                <div>
                    <h3 class="vs-modal__titulo">Anular venta</h3>
                    <p class="vs-modal__subtitulo">{{ $codAnular }} · S/ {{ number_format($va->total, 2) }}</p>
                </div>
                <button class="vs-modal__cerrar" wire:click="cerrarAnular">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="vs-modal__body">

                <div class="vs-anular-alerta">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                    </svg>
                    <div>
                        <p class="vs-anular-alerta__titulo">Esta acción no se puede deshacer</p>
                        <p class="vs-anular-alerta__texto">Se registrará una devolución de <strong>S/ {{ number_format($va->total, 2) }}</strong> y la venta quedará marcada como anulada.</p>
                    </div>
                </div>

                <p class="vs-modal__section-label" style="margin-bottom:.5rem">Devolución a registrar</p>
                <div class="vs-modal__pagos" style="margin-bottom:1rem">
                    @foreach($va->pagos as $pago)
                        <div class="vs-modal__pago-item">
                            <span class="vs-modal__pago-metodo">{{ $pago->metodoPago?->nombre ?? '—' }}</span>
                            <span class="vs-modal__pago-monto">S/ {{ number_format($pago->monto, 2) }}</span>
                        </div>
                    @endforeach
                </div>

                @if($tieneStock)
                    <label class="vs-anular-check-label">
                        <input type="checkbox" class="vs-anular-check" wire:model.live="revertirStock">
                        <span class="vs-anular-check-text">
                            <strong>Revertir inventario</strong><br>
                            <span>Se devolverá el stock de los productos con control de inventario y se registrará en el kardex.</span>
                        </span>
                    </label>
                @else
                    <p class="vs-anular-sin-stock">Ningún producto de esta venta tiene control de stock.</p>
                @endif

                {{-- SUNAT: baja si el comprobante ya fue enviado --}}
                @if($necesitaBaja)
                    <div class="vs-anular-sunat-aviso">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/>
                        </svg>
                        <div>
                            <p class="vs-anular-sunat__titulo">Se enviará Comunicación de Baja a SUNAT</p>
                            <p class="vs-anular-sunat__texto">Este comprobante ya fue registrado ante SUNAT ({{ $va->estado_sunat?->getLabel() }}). Se generará y enviará automáticamente una Baja (RA).</p>
                        </div>
                    </div>
                    <div class="vs-anular-motivo">
                        <label class="vs-anular-motivo__label" for="rv-motivo-baja">Motivo de anulación (SUNAT)</label>
                        <input
                            type="text"
                            id="rv-motivo-baja"
                            class="vs-anular-motivo__input"
                            wire:model="motivoBaja"
                            placeholder="Error en emisión"
                            maxlength="100"
                        />
                    </div>
                @endif

            </div>

            <div class="vs-modal__footer vs-modal__footer--anular">
                <button class="vs-modal__btn-cerrar" wire:click="cerrarAnular">Cancelar</button>
                <button class="vs-btn-confirmar-anular" wire:click="confirmarAnular" wire:loading.attr="disabled">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                    <span wire:loading.remove wire:target="confirmarAnular">Confirmar anulación</span>
                    <span wire:loading wire:target="confirmarAnular">Procesando…</span>
                </button>
            </div>

        </div>
    </div>
    @endif
@endif

{{-- ══ IFRAME IMPRESIÓN EN PÁGINA ══ --}}
<div x-data="vsTicketPrint()"
     @pdv-imprimir-ticket.window="cargar($event.detail.url)">
    <iframe id="vs-ticket-frame"
        style="position:fixed;left:-9999px;top:-9999px;width:1px;height:1px;border:0;opacity:0;"
        @load="onLoad()"></iframe>
</div>

{{-- ══ MODAL CONVERTIR TICKET ══ --}}
@if($modalConvertir)
<div class="vs-overlay" wire:key="modal-convertir-rv">
    <div class="vs-overlay__backdrop" wire:click="cerrarConvertir"></div>
    <div class="vs-modal vs-modal--convertir">

        <div class="vs-modal__header">
            <div>
                <h3 class="vs-modal__titulo">Emitir comprobante electrónico</h3>
                <p class="vs-modal__subtitulo">Ticket <strong>{{ $convertirCodigo }}</strong> · S/ {{ number_format($convertirTotal, 2) }}</p>
            </div>
            <button class="vs-modal__cerrar" wire:click="cerrarConvertir">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="vs-conv-tipo-wrap">
            <button class="vs-conv-tipo-btn {{ $convertirTipo === 'boleta' ? 'vs-conv-tipo-btn--active' : '' }}" wire:click="$set('convertirTipo', 'boleta')">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185Z"/></svg>
                Boleta
            </button>
            <button class="vs-conv-tipo-btn {{ $convertirTipo === 'factura' ? 'vs-conv-tipo-btn--active' : '' }}" wire:click="$set('convertirTipo', 'factura')">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                Factura
            </button>
        </div>

        <div class="vs-conv-section">
            <label class="vs-conv-label">{{ $convertirTipo === 'factura' ? 'Cliente (RUC requerido)' : 'Cliente (opcional)' }}</label>
            <div class="vs-conv-search-wrap">
                <div class="vs-conv-search-row">
                    <input type="text" class="vs-conv-input" wire:model.live="convertirBusqueda"
                        placeholder="Buscar por nombre o documento…" autocomplete="off"/>
                    @if($convertirClienteId)
                        <button class="vs-conv-clear-btn" wire:click="limpiarConvertirCliente" title="Quitar cliente">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                        </button>
                    @endif
                </div>
                @if($convertirClienteId)
                    <div class="vs-conv-cliente-sel">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                        <span><strong>{{ $convertirClienteNombre }}</strong> · {{ strtoupper($convertirClienteTipoDoc) }} {{ $convertirClienteNumDoc }}</span>
                    </div>
                @endif
                @if($convertirMostrarSug)
                    @php $sug = $this->getConvertirSugeridos(); @endphp
                    @if($sug->isNotEmpty())
                        <ul class="vs-conv-sugerencias">
                            @foreach($sug as $s)
                                <li wire:click="seleccionarConvertirCliente({{ $s->id }})" wire:key="sug-rv-{{ $s->id }}">
                                    <span class="vs-conv-sug-nombre">{{ $s->nombre_completo }}</span>
                                    <span class="vs-conv-sug-doc">{{ strtoupper($s->tipo_documento->value) }} {{ $s->numero_documento }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="vs-conv-sug-empty">Sin resultados</p>
                    @endif
                @endif
            </div>
        </div>

        <div class="vs-conv-igv-preview">
            <div class="vs-conv-igv-row"><span>Op. Gravadas</span><span>S/ {{ number_format($convertirOpGravadas, 2) }}</span></div>
            <div class="vs-conv-igv-row"><span>IGV ({{ $convertirIgvPct }}%)</span><span>S/ {{ number_format($convertirIgv, 2) }}</span></div>
            <div class="vs-conv-igv-row vs-conv-igv-row--total"><span>Total</span><span>S/ {{ number_format($convertirTotal, 2) }}</span></div>
        </div>

        <div class="vs-modal__footer">
            <button class="vs-modal__btn-cerrar" wire:click="cerrarConvertir">Cancelar</button>
            <button class="vs-btn-confirmar-convertir" wire:click="confirmarConvertir" wire:loading.attr="disabled">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                <span wire:loading.remove wire:target="confirmarConvertir">Emitir {{ $convertirTipo }}</span>
                <span wire:loading wire:target="confirmarConvertir">Procesando…</span>
            </button>
        </div>

    </div>
</div>
@endif

<script>
function vsTicketPrint() {
    return {
        pendingPrint: false,
        cargar(url) {
            this.pendingPrint = true;
            const iframe = document.getElementById('vs-ticket-frame');
            iframe.src = url;
        },
        onLoad() {
            if (!this.pendingPrint) return;
            this.pendingPrint = false;
            const iframe = document.getElementById('vs-ticket-frame');
            if (iframe && iframe.contentWindow) {
                iframe.contentWindow.print();
            }
        }
    };
}
</script>

</x-filament-panels::page>

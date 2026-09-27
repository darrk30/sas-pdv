<x-filament-panels::page>
<div>
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
@if($ajusteDetalleId)
    @php $ad = $this->getAjusteDetalle(); @endphp
    @if($ad)
    <div class="vs-overlay" wire:key="raj-modal-{{ $ad->id }}">
        <div class="vs-overlay__backdrop" wire:click="cerrarDetalle"></div>
        <div class="vs-modal" style="max-width:40rem">

            <div class="vs-modal__header">
                <div>
                    <h3 class="vs-modal__titulo">{{ $ad->codigo ?? 'Ajuste #' . $ad->id }}</h3>
                    <p class="vs-modal__subtitulo">
                        {{ $ad->created_at->format('d/m/Y H:i') }}
                        · {{ $ad->responsable?->name ?? '—' }}
                    </p>
                </div>
                <div class="vs-modal__badges">
                    <span class="rc-badge rc-badge--{{ $ad->tipo }}">
                        {{ $ad->tipo === 'entrada' ? 'Entrada' : 'Salida' }}
                    </span>
                    <span class="rc-badge rc-badge--{{ $ad->estado }}">
                        {{ $ad->estado === 'confirmado' ? 'Confirmado' : 'Borrador' }}
                    </span>
                </div>
                <button class="vs-modal__cerrar" wire:click="cerrarDetalle">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="vs-modal__cliente">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z"/>
                </svg>
                <span><strong>Motivo:</strong> {{ $ad->motivo }}</span>
            </div>

            <div class="vs-modal__body">
                <p class="rc-modal-section-label">Productos ajustados ({{ $ad->detalles->count() }})</p>
                <div style="overflow-x:auto">
                    <table class="rc-modal-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th class="rc-ta-right">Cantidad</th>
                                <th class="rc-ta-right">Costo unit.</th>
                                <th class="rc-ta-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($ad->detalles as $d)
                                <tr>
                                    <td>{{ $d->nombre_producto }}</td>
                                    <td class="rc-ta-right">
                                        {{ number_format($d->cantidad, 2) }}
                                        <span style="font-size:.65rem;color:var(--vs-text-muted)">{{ $d->unidad?->simbolo }}</span>
                                    </td>
                                    <td class="rc-ta-right">S/ {{ number_format($d->costo_unitario, 4) }}</td>
                                    <td class="rc-ta-right">S/ {{ number_format($d->costo_total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="rc-modal-totales">
                    <div class="rc-modal-total-fila rc-modal-total-fila--grande">
                        <span>Valor total</span>
                        <span>S/ {{ number_format((float)$ad->valor_total, 2) }}</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
    @endif
@endif

</x-filament-panels::page>

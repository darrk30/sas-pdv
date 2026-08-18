<?php

namespace App\Filament\Pdv\Resources\Compras\Pages;

use App\Filament\Pdv\Resources\Compras\CompraResource;
use App\Services\InventarioCoreService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class EditCompra extends EditRecord
{
    protected static string $resource = CompraResource::class;

    /**
     * Snapshot del estado y detalles al CARGAR el formulario, antes de cualquier edición.
     * Se usa como referencia para calcular el delta de stock al guardar.
     * #[Locked] impide que el cliente modifique estos valores entre roundtrips de Livewire.
     *
     * No se captura en mutateFormDataBeforeSave porque en Filament v5 las relaciones
     * (compra_detalles) ya están guardadas en BD antes de que ese hook se ejecute.
     */
    #[Locked]
    public string $snapshotEstado = 'pendiente';

    #[Locked]
    public array $snapshotDetalles = [];

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $tipo = $data['tipo_comprobante'] ?? '';
        $tipo = $tipo instanceof \BackedEnum ? $tipo->value : (string) $tipo;

        if ($tipo !== 'sin_comprobante') {
            $serie       = $data['serie'] ?? null;
            $correlativo = $data['correlativo'] ?? null;

            if ($serie && $correlativo) {
                $codigo    = $serie . '-' . $correlativo;
                $empresaId = Filament::getTenant()?->id;

                $existe = DB::table('compras')
                    ->where('empresa_id', $empresaId)
                    ->where('codigo', $codigo)
                    ->where('id', '!=', $this->getRecord()->getKey())
                    ->exists();

                if ($existe) {
                    throw ValidationException::withMessages([
                        'data.correlativo' => "El comprobante {$codigo} ya está registrado.",
                    ]);
                }
            }
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('anular')
                ->label('Anular compra')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('¿Anular compra?')
                ->modalDescription(fn() => $this->record->estaRecibida()
                    ? 'La compra está recibida: se revertirá el stock ingresado. Esta acción no se puede deshacer.'
                    : 'La compra será marcada como anulada. Esta acción no se puede deshacer.')
                ->modalSubmitActionLabel('Sí, anular')
                ->visible(fn() => ! $this->record->estaAnulada())
                ->action(function (): void {
                    $record      = $this->record;
                    $eraRecibida = $record->estaRecibida();

                    if ($eraRecibida) {
                        app(InventarioCoreService::class)->revertirCompra($record);
                    }
                    $record->update(['estado' => 'anulado']);

                    Notification::make()
                        ->warning()
                        ->title('Compra ' . $record->codigo . ' anulada')
                        ->body($eraRecibida ? 'La compra fue anulada y el stock revertido.' : 'La compra fue anulada.')
                        ->send();

                    $this->redirect(static::getResource()::getUrl('index'));
                }),

            DeleteAction::make()
                ->visible(fn() => ! $this->record->estaAnulada()),
        ];
    }

    /**
     * Se ejecuta después de que Filament llena el formulario con los datos del registro.
     * Es el único momento seguro para capturar el estado "antes de editar".
     */
    protected function afterFill(): void
    {
        $record = $this->getRecord();

        $this->snapshotEstado   = $record->estado_despacho ?? 'pendiente';
        $this->snapshotDetalles = DB::table('compra_detalles')
            ->where('compra_id', $record->getKey())
            ->get(['producto_id', 'variante_id', 'unidad_id', 'cantidad'])
            ->map(fn(\stdClass $row) => (array) $row)
            ->values()
            ->all();
    }

    /**
     * Se ejecuta después de que Filament guarda el registro principal Y las relaciones.
     * Compara el snapshot (estado pre-edición) contra el estado actual en BD para
     * determinar qué movimiento de stock aplicar.
     */
    protected function afterSave(): void
    {
        $record      = $this->getRecord()->refresh();
        $estadoViejo = $this->snapshotEstado;
        $estadoNuevo = $record->estado_despacho;
        $service     = app(InventarioCoreService::class);
        $concepto    = $record->codigo ?? ('COMPRA-' . str_pad($record->id, 8, '0', STR_PAD_LEFT));
        $userId      = auth()->id();

        // Ítems afectados = unión de lo que había antes + lo que hay ahora
        $nuevos = $record->detalles()->with('unidad')->get();

        if ($estadoViejo !== $estadoNuevo) {
            if ($estadoNuevo === 'recibido') {
                // pendiente → recibido: aplicar cantidades actuales con kardex
                $service->aplicarCompra($record);
            } else {
                // recibido → pendiente: revertir exactamente lo que se recibió (snapshot)
                $service->revertirDetalles(
                    $record->empresa_id,
                    'entrada',
                    collect($this->snapshotDetalles),
                    $record,
                    $concepto . ' (reversión)',
                    $userId,
                );
            }
        } elseif ($estadoNuevo === 'recibido') {
            // recibido → recibido con cambios: revertir snapshot + aplicar nuevos en una sola tx
            DB::transaction(function () use ($record, $service, $nuevos, $concepto, $userId): void {
                $service->revertirDetalles($record->empresa_id, 'entrada', collect($this->snapshotDetalles), $record, $concepto . ' (reversión)', $userId);
                $service->aplicarDetalles($record->empresa_id, 'entrada', $nuevos, $record, $concepto, $userId);
            });

            // Tras el revert + re-apply, stock_reserva puede quedar desfasado si había
            // órdenes pendientes que lo tenían consumido. Recalculamos desde las órdenes reales.
            $afectados = $this->_afectadosUnion(collect($this->snapshotDetalles), $nuevos);
            $this->_recalcularReservas($record->empresa_id, $afectados);
        }
        // pendiente → pendiente: sin acción de stock

        // Actualizar snapshot para la próxima edición en la misma sesión (SPA mode)
        $this->snapshotEstado   = $estadoNuevo;
        $this->snapshotDetalles = DB::table('compra_detalles')
            ->where('compra_id', $record->getKey())
            ->get(['producto_id', 'variante_id', 'unidad_id', 'cantidad'])
            ->map(fn(\stdClass $row) => (array) $row)
            ->values()
            ->all();
    }

    // ── Colecta todos los (producto_id, variante_id) únicos de dos colecciones ──

    private function _afectadosUnion(Collection $snapshot, Collection $nuevos): array
    {
        $pares = [];

        foreach ($snapshot as $item) {
            $key = ($item['variante_id'] ?? 0) . '_' . ($item['producto_id'] ?? 0);
            $pares[$key] = ['producto_id' => $item['producto_id'] ?? null, 'variante_id' => $item['variante_id'] ?? null];
        }

        foreach ($nuevos as $item) {
            $varId  = $item->variante_id ?? null;
            $prodId = $item->producto_id ?? null;
            $key    = ($varId ?? 0) . '_' . ($prodId ?? 0);
            $pares[$key] = ['producto_id' => $prodId, 'variante_id' => $varId];
        }

        return array_values($pares);
    }

    // ── Recalcula stock_reserva basándose en órdenes pendientes reales ──────────
    // Garantiza que stock_reserva = max(0, stock_real − cantidad_reservada_por_órdenes).
    // Cubre órdenes directas (producto/variante) y órdenes de promoción (via promo_detalles).

    private function _recalcularReservas(int $empresaId, array $afectados): void
    {
        foreach ($afectados as $par) {
            $productoId = $par['producto_id'] ?? null;
            $varianteId = $par['variante_id'] ?? null;

            if (! $productoId && ! $varianteId) continue;

            // ── Órdenes directas (pendiente_pago, no promo) ───────────────────
            $directa = (float) DB::table('orden_detalles as od')
                ->join('ordenes as o', 'o.id', '=', 'od.orden_id')
                ->where('o.empresa_id', $empresaId)
                ->where('o.estado', 'pendiente_pago')
                ->whereNull('od.promocion_id')
                ->when($varianteId,
                    fn($q) => $q->where('od.variante_id', $varianteId),
                    fn($q) => $q->where('od.producto_id', $productoId)->whereNull('od.variante_id'),
                )
                ->sum('od.cantidad');

            // ── Órdenes via promoción ─────────────────────────────────────────
            $porPromo = (float) DB::table('orden_detalles as od')
                ->join('ordenes as o', 'o.id', '=', 'od.orden_id')
                ->join('promocion_detalles as pd', 'pd.promocion_id', '=', 'od.promocion_id')
                ->where('o.empresa_id', $empresaId)
                ->where('o.estado', 'pendiente_pago')
                ->whereNotNull('od.promocion_id')
                ->when($varianteId,
                    fn($q) => $q->where('pd.variante_id', $varianteId),
                    fn($q) => $q->where('pd.producto_id', $productoId)->whereNull('pd.variante_id'),
                )
                ->selectRaw('COALESCE(SUM(od.cantidad * pd.cantidad), 0) as total')
                ->value('total');

            $reservado = $directa + $porPromo;

            // ── Actualizar inventario ─────────────────────────────────────────
            $invQuery = DB::table('inventarios')
                ->where('empresa_id', $empresaId);

            if ($varianteId) {
                $invQuery->where('variante_id', $varianteId);
            } else {
                $invQuery->where('producto_id', $productoId)->whereNull('variante_id');
            }

            $inv = $invQuery->lockForUpdate()->first();
            if (! $inv) continue;

            $nuevoReserva = max(0.0, (float) $inv->stock_real - $reservado);
            $invQuery->update(['stock_reserva' => $nuevoReserva]);
        }
    }
}

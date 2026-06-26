<?php

namespace App\Filament\Pdv\Resources\Compras\Pages;

use App\Filament\Pdv\Resources\Compras\CompraResource;
use App\Services\InventarioCoreService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
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
            $nuevos = $record->detalles()->with('unidad')->get();
            DB::transaction(function () use ($record, $service, $nuevos, $concepto, $userId): void {
                $service->revertirDetalles($record->empresa_id, 'entrada', collect($this->snapshotDetalles), $record, $concepto . ' (reversión)', $userId);
                $service->aplicarDetalles($record->empresa_id, 'entrada', $nuevos, $record, $concepto, $userId);
            });
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
}

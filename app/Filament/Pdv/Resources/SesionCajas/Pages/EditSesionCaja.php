<?php

namespace App\Filament\Pdv\Resources\SesionCajas\Pages;

use App\Enums\EstadoMovimiento;
use App\Enums\EstadoSesion;
use App\Enums\TipoMovimiento;
use App\Filament\Pdv\Resources\SesionCajas\SesionCajaResource;
use App\Filament\Pdv\Resources\SesionCajas\Schemas\SesionCajaForm;
use App\Models\SesionCaja;
use App\Models\SesionCajaPago;
use App\Models\Transaccion;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;

class EditSesionCaja extends EditRecord
{
    protected static string $resource = SesionCajaResource::class;

    public function mount(int|string $record): void
    {
        // Cargar el modelo para sincronizar pagos ANTES de que parent::mount()
        // llene el formulario (el Repeater carga la relación durante fillForm)
        $sesion = SesionCaja::find($record);

        if ($sesion?->estaAbierta()) {
            $this->sincronizarPagosSistema($sesion);
        }

        parent::mount($record);

        // Redirigir si la sesión ya estaba cerrada
        if (! $this->record->estaAbierta()) {
            Notification::make()
                ->warning()
                ->title('Esta sesión ya está cerrada')
                ->body('No se puede modificar una sesión cerrada.')
                ->send();

            $this->redirect(static::getResource()::getUrl('index'));
        }
    }

    public function form(Schema $schema): Schema
    {
        return SesionCajaForm::configureCierre($schema);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $totales = $this->calcularTotalesPorMetodo($this->record);

        $sistemaTotal             = round($totales->sum(), 2);
        $data['total_sistema']    = $sistemaTotal;
        $cajero                   = round((float) $this->record->pagos->sum('importe_cajero'), 2);
        $data['total_cajero']     = $cajero;
        $data['diferencia_total'] = round($cajero - $sistemaTotal, 2);
        $data['total_creditos']   = $this->calcularCreditosSesion($this->record);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['estado']       = EstadoSesion::Cerrada->value;
        $data['fecha_cierre'] = now();

        return $data;
    }

    // Ejecuta DESPUÉS de que el Repeater sincroniza los SesionCajaPago en BD
    protected function afterSave(): void
    {
        $sesion  = $this->record;
        $totales = $this->calcularTotalesPorMetodo($sesion);

        // Recalcular importe_sistema y diferencia por fila con valores reales de BD
        foreach ($sesion->pagos as $pago) {
            $sistema = round((float) ($totales[$pago->metodo_pago_id] ?? 0), 2);
            $cajero  = round((float) ($pago->importe_cajero ?? 0), 2);
            $pago->update([
                'importe_sistema' => $sistema,
                'diferencia'      => round($cajero - $sistema, 2),
            ]);
        }

        $cajeroTotal  = round((float) $sesion->pagos()->sum('importe_cajero'), 2);
        $sistemaTotal = round($totales->sum(), 2);

        $sesion->update([
            'total_sistema'    => $sistemaTotal,
            'total_cajero'     => $cajeroTotal,
            'diferencia_total' => round($cajeroTotal - $sistemaTotal, 2),
            'total_creditos'   => $this->calcularCreditosSesion($sesion),
        ]);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Sesión cerrada correctamente';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn() => $this->record->estaAbierta()),
        ];
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function sincronizarPagosSistema(SesionCaja $sesion): void
    {
        $totales = $this->calcularTotalesPorMetodo($sesion);

        foreach ($totales as $mpId => $importe) {
            $importe = round($importe, 2);
            // firstOrCreate: si ya existe, no toca importe_cajero; si es nuevo, lo inicializa en 0
            $pago = SesionCajaPago::firstOrCreate(
                ['sesion_caja_id' => $sesion->id, 'metodo_pago_id' => $mpId],
                ['importe_sistema' => $importe, 'importe_cajero' => 0, 'diferencia' => 0],
            );
            // Siempre actualizar importe_sistema (las ventas pueden haber cambiado)
            $pago->update(['importe_sistema' => $importe]);
        }

        // Eliminar filas de métodos que ya no tienen transacciones
        $q = $sesion->pagos();
        if ($totales->isNotEmpty()) {
            $q->whereNotIn('metodo_pago_id', $totales->keys()->all());
        }
        $q->delete();
    }

    private function calcularCreditosSesion(SesionCaja $sesion): float
    {
        return round((float) Transaccion::where('sesion_caja_id', $sesion->id)
            ->where('estado', EstadoMovimiento::PorCobrar->value)
            ->sum('monto'), 2);
    }

    private function calcularTotalesPorMetodo(SesionCaja $sesion): Collection
    {
        return Transaccion::where('sesion_caja_id', $sesion->id)
            ->where('estado', 'aprobado')
            ->whereNotNull('metodo_pago_id')
            ->get()
            ->groupBy('metodo_pago_id')
            ->map(fn($rows) => $rows->sum(
                fn($t) => $t->tipo === TipoMovimiento::Ingreso
                    ? (float) $t->monto
                    : -(float) $t->monto
            ));
    }
}

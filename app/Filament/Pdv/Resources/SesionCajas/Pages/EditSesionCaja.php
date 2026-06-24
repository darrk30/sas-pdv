<?php

namespace App\Filament\Pdv\Resources\SesionCajas\Pages;

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
        // Mostrar total sistema calculado en el formulario
        $totales               = $this->calcularTotalesPorMetodo($this->record);
        $data['total_sistema'] = round($totales->sum(), 2);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // ── SEGURIDAD: siempre recalcular desde BD, ignorar valores enviados ──
        $totales = $this->calcularTotalesPorMetodo($this->record);

        $pagos       = $data['pagos'] ?? [];
        $cajeroTotal = 0;

        foreach ($pagos as &$pago) {
            $mpId    = $pago['metodo_pago_id'];
            $sistema = round((float) ($totales[$mpId] ?? 0), 2);
            $cajero  = round((float) ($pago['importe_cajero'] ?? 0), 2);

            $pago['importe_sistema'] = $sistema;          // sobrescribe lo enviado
            $pago['diferencia']      = round($cajero - $sistema, 2);
            $cajeroTotal            += $cajero;
        }
        unset($pago);

        $sistemaTotal = round($totales->sum(), 2);
        $cajeroTotal  = round($cajeroTotal, 2);

        $data['pagos']            = $pagos;
        $data['total_sistema']    = $sistemaTotal;
        $data['total_cajero']     = $cajeroTotal;
        $data['diferencia_total'] = round($cajeroTotal - $sistemaTotal, 2);
        $data['estado']           = EstadoSesion::Cerrada->value;
        $data['fecha_cierre']     = now();

        return $data;
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
            SesionCajaPago::updateOrCreate(
                ['sesion_caja_id' => $sesion->id, 'metodo_pago_id' => $mpId],
                ['importe_sistema' => round($importe, 2)],
            );
        }

        // Eliminar filas de métodos que ya no tienen transacciones
        $q = $sesion->pagos();
        if ($totales->isNotEmpty()) {
            $q->whereNotIn('metodo_pago_id', $totales->keys()->all());
        }
        $q->delete();
    }

    private function calcularTotalesPorMetodo(SesionCaja $sesion): Collection
    {
        return Transaccion::where('sesion_caja_id', $sesion->id)
            ->where('estado', 'aprobado')
            ->get()
            ->groupBy('metodo_pago_id')
            ->map(fn($rows) => $rows->sum(
                fn($t) => $t->tipo === TipoMovimiento::Ingreso
                    ? (float) $t->monto
                    : -(float) $t->monto
            ));
    }
}

<?php

namespace App\Filament\Pdv\Concerns;

use App\Enums\EstadoSunat;
use App\Enums\TipoComprobante;
use App\Events\VentaCompletada;
use App\Models\Cliente;
use App\Models\Serie;
use App\Models\Venta;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait HasConvertirComprobante
{
    // ── Propiedades ───────────────────────────────────────────────────────────

    public ?int   $convertirVentaId       = null;
    public bool   $modalConvertir         = false;
    public string $convertirTipo          = 'boleta';
    public ?int   $convertirClienteId     = null;
    public string $convertirClienteNombre = '';
    public string $convertirClienteTipoDoc= '';
    public string $convertirClienteNumDoc = '';
    public string $convertirBusqueda      = '';
    public bool   $convertirMostrarSug    = false;
    // display-only, calculados al abrir
    public string $convertirCodigo        = '';
    public float  $convertirTotal         = 0.0;
    public float  $convertirIgvPct        = 18.0;
    public float  $convertirOpGravadas    = 0.0;
    public float  $convertirIgv           = 0.0;

    // ── Action builder (llamar desde table()) ─────────────────────────────────

    public function buildConvertirAction(): Action
    {
        return Action::make('convertirComprobante')
            ->label('Emitir boleta / factura')
            ->icon('heroicon-o-arrow-up-circle')
            ->color('info')
            ->visible(fn (Venta $record): bool =>
                $record->serie?->tipo === TipoComprobante::Ticket
                && ! $record->estaAnulada()
                && Filament::getTenant()->tieneFacturacionElectronica()
            )
            ->action(fn (Venta $record) => $this->abrirConvertir($record->id));
    }

    // ── Métodos ───────────────────────────────────────────────────────────────

    public function abrirConvertir(int $ventaId): void
    {
        $venta = Venta::with('serie')->find($ventaId);
        if (! $venta) return;

        $igvPct  = (float) (Filament::getTenant()->igv_porcentaje ?? 18);
        $total   = (float) $venta->total;
        $divisor = 1 + $igvPct / 100;

        $this->convertirVentaId        = $ventaId;
        $this->convertirTipo           = 'boleta';
        $this->convertirClienteId      = $venta->cliente_id;
        $this->convertirClienteNombre  = $venta->cliente_nombre ?? '';
        $this->convertirClienteTipoDoc = $venta->cliente_tipo_doc ?? 'dni';
        $this->convertirClienteNumDoc  = $venta->cliente_num_doc ?? '';
        $this->convertirBusqueda       = $venta->cliente_nombre ?? '';
        $this->convertirMostrarSug     = false;
        $this->convertirCodigo         = ($venta->serie?->serie ?? '?') . '-' . str_pad($venta->correlativo, 8, '0', STR_PAD_LEFT);
        $this->convertirTotal          = $total;
        $this->convertirIgvPct         = $igvPct;
        $this->convertirOpGravadas     = round($total / $divisor, 2);
        $this->convertirIgv            = round($total - round($total / $divisor, 2), 2);
        $this->modalConvertir          = true;
    }

    public function cerrarConvertir(): void
    {
        $this->convertirVentaId        = null;
        $this->modalConvertir          = false;
        $this->convertirTipo           = 'boleta';
        $this->convertirClienteId      = null;
        $this->convertirClienteNombre  = '';
        $this->convertirClienteTipoDoc = '';
        $this->convertirClienteNumDoc  = '';
        $this->convertirBusqueda       = '';
        $this->convertirMostrarSug     = false;
        $this->convertirCodigo         = '';
        $this->convertirTotal          = 0.0;
        $this->convertirIgvPct         = 18.0;
        $this->convertirOpGravadas     = 0.0;
        $this->convertirIgv            = 0.0;
    }

    public function updatedConvertirBusqueda(): void
    {
        $this->convertirMostrarSug = strlen($this->convertirBusqueda) >= 2;
        if ($this->convertirClienteId && $this->convertirBusqueda !== $this->convertirClienteNombre) {
            $this->convertirClienteId      = null;
            $this->convertirClienteNombre  = '';
            $this->convertirClienteTipoDoc = '';
            $this->convertirClienteNumDoc  = '';
        }
    }

    public function getConvertirSugeridos(): Collection
    {
        if (strlen($this->convertirBusqueda) < 2) return collect();

        return Cliente::where('empresa_id', Filament::getTenant()->id)
            ->where(function ($q) {
                $q->where('nombre', 'like', "%{$this->convertirBusqueda}%")
                  ->orWhere('apellidos', 'like', "%{$this->convertirBusqueda}%")
                  ->orWhere('numero_documento', 'like', "%{$this->convertirBusqueda}%");
            })
            ->limit(6)
            ->get();
    }

    public function seleccionarConvertirCliente(int $id): void
    {
        $cliente = Cliente::find($id);
        if (! $cliente) return;

        $this->convertirClienteId      = $id;
        $this->convertirClienteNombre  = $cliente->nombre_completo;
        $this->convertirClienteTipoDoc = $cliente->tipo_documento->value;
        $this->convertirClienteNumDoc  = $cliente->numero_documento;
        $this->convertirBusqueda       = $cliente->nombre_completo;
        $this->convertirMostrarSug     = false;

        if ($cliente->tipo_documento->value === 'ruc') {
            $this->convertirTipo = 'factura';
        }
    }

    public function limpiarConvertirCliente(): void
    {
        $this->convertirClienteId      = null;
        $this->convertirClienteNombre  = '';
        $this->convertirClienteTipoDoc = '';
        $this->convertirClienteNumDoc  = '';
        $this->convertirBusqueda       = '';
        $this->convertirMostrarSug     = false;
    }

    public function confirmarConvertir(): void
    {
        if (! $this->convertirVentaId) return;

        $venta = Venta::with(['serie', 'detalles.producto'])->find($this->convertirVentaId);

        if (! $venta || $venta->empresa_id !== Filament::getTenant()->id) {
            Notification::make()->title('Venta no encontrada')->danger()->send();
            return;
        }

        if ($venta->serie?->tipo !== TipoComprobante::Ticket) {
            Notification::make()->title('Esta venta no es un ticket')->warning()->send();
            return;
        }

        if ($venta->estaAnulada()) {
            Notification::make()->title('No se puede convertir una venta anulada')->warning()->send();
            return;
        }

        $tipoDestino = $this->convertirTipo === 'factura'
            ? TipoComprobante::Factura
            : TipoComprobante::Boleta;

        if ($tipoDestino === TipoComprobante::Factura) {
            $numDoc = preg_replace('/\D/', '', $this->convertirClienteNumDoc);
            if (strlen($numDoc) !== 11) {
                Notification::make()->title('La factura requiere el RUC del cliente (11 dígitos)')->danger()->send();
                return;
            }
        }

        $serie = Serie::where('empresa_id', Filament::getTenant()->id)
            ->where('tipo', $tipoDestino->value)
            ->where('estado', true)
            ->first();

        if (! $serie) {
            $label = $tipoDestino === TipoComprobante::Factura ? 'factura' : 'boleta';
            Notification::make()->title("No hay serie activa de {$label} configurada")->danger()->send();
            return;
        }

        $empresa    = Filament::getTenant();
        $igvPct     = (float) ($empresa->igv_porcentaje ?? 18);
        $divisor    = 1 + $igvPct / 100;
        $total      = (float) $venta->total;
        $opGravadas = round($total / $divisor, 2);
        $igv        = round($total - $opGravadas, 2);

        $clienteId      = $this->convertirClienteId;
        $clienteNombre  = trim($this->convertirClienteNombre) ?: ($venta->cliente_nombre ?? 'CLIENTE VARIOS');
        $clienteTipoDoc = $this->convertirClienteTipoDoc ?: ($tipoDestino === TipoComprobante::Factura ? 'ruc' : 'dni');
        $clienteNumDoc  = trim($this->convertirClienteNumDoc) ?: ($venta->cliente_num_doc ?? '-');

        $nuevoCorrelativo = null;

        try {
            DB::transaction(function () use (
                $venta, $serie, $opGravadas, $igv,
                $clienteId, $clienteNombre, $clienteTipoDoc, $clienteNumDoc,
                &$nuevoCorrelativo
            ) {
                $serieLocked      = Serie::lockForUpdate()->findOrFail($serie->id);
                $nuevoCorrelativo = $serieLocked->numero + 1;
                $serieLocked->update(['numero' => $nuevoCorrelativo]);

                $venta->update([
                    'serie_id'          => $serieLocked->id,
                    'correlativo'       => str_pad($nuevoCorrelativo, 8, '0', STR_PAD_LEFT),
                    'op_gravadas'       => $opGravadas,
                    'op_inafectas'      => 0,
                    'op_exoneradas'     => 0,
                    'igv'               => $igv,
                    'cliente_id'        => $clienteId,
                    'cliente_nombre'    => $clienteNombre,
                    'cliente_tipo_doc'  => $clienteTipoDoc,
                    'cliente_num_doc'   => $clienteNumDoc,
                    'hash'              => null,
                    'path_xml'          => null,
                    'path_cdr_zip'      => null,
                    'sunat_success'     => false,
                    'sunat_codigo'      => null,
                    'sunat_descripcion' => null,
                    'sunat_mensaje'     => null,
                    'sunat_notas'       => null,
                    'estado_sunat'      => EstadoSunat::PorEnviar,
                    'qr_data'           => null,
                    'total_letras'      => null,
                    'resumen_sunat_id'  => null,
                ]);
            });
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error al convertir la venta')
                ->body($e->getMessage())
                ->danger()
                ->send();
            return;
        }

        $venta->refresh();
        $venta->load(['serie', 'detalles.producto', 'empresa']);
        VentaCompletada::dispatch($venta);

        $nuevoComprobante = ($venta->serie?->serie ?? '?') . '-' . str_pad($venta->correlativo, 8, '0', STR_PAD_LEFT);

        $this->cerrarConvertir();

        Notification::make()
            ->title("Ticket convertido a {$nuevoComprobante}")
            ->body('El comprobante electrónico está siendo procesado.')
            ->success()
            ->send();
    }
}

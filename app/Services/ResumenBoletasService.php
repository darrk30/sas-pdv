<?php

namespace App\Services;

use App\Enums\EstadoSunat;
use App\Enums\EstadoVenta;
use App\Enums\TipoComprobante;
use App\Models\Empresa;
use App\Models\Nota;
use App\Models\ResumenSunat;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class ResumenBoletasService
{
    public function __construct(private FacturadorService $facturador) {}

    /**
     * Genera y envía el RC diario al facturador (usado por la página manual).
     * Filtra boletas por fecha de emisión. En caso de error de la API, marca el RC como Error.
     * Retorna array con: ok (bool), mensaje (string), vacio (bool), resumen (?ResumenSunat)
     */
    public function enviarResumenRc(Empresa $empresa, Carbon $fecha, ?array $ventaIds = null): array
    {
        if (! $empresa->tieneFacturacionElectronica()) {
            return ['ok' => false, 'mensaje' => 'Empresa sin configuración de facturación electrónica.'];
        }

        $qNuevas = Venta::where('empresa_id', $empresa->id)
            ->with('serie')
            ->whereHas('serie', fn ($q) => $q->where('tipo', TipoComprobante::Boleta->value))
            ->where('estado_sunat', EstadoSunat::PorEnviar->value)
            ->where('estado', '!=', EstadoVenta::Anulada->value)
            ->whereNull('resumen_sunat_id')
            ->whereDate('fecha_emision', $fecha);

        if ($ventaIds) {
            $qNuevas->whereIn('id', $ventaIds);
        }

        $ventasNuevas = $qNuevas->get();

        $qBaja = Venta::where('empresa_id', $empresa->id)
            ->with('serie')
            ->whereHas('serie', fn ($q) => $q->where('tipo', TipoComprobante::Boleta->value))
            ->where('estado_sunat', EstadoSunat::PorDarBaja->value)
            ->whereNull('resumen_sunat_id')
            ->whereDate('fecha_emision', $fecha);

        if ($ventaIds) {
            $qBaja->whereIn('id', $ventaIds);
        }

        $ventasBaja = $qBaja->get();
        $ventas     = $ventasNuevas->merge($ventasBaja);

        if ($ventas->isEmpty()) {
            return [
                'ok'    => false,
                'vacio' => true,
                'mensaje' => 'No hay boletas pendientes para el ' . $fecha->format('d/m/Y') . '.',
            ];
        }

        return $this->crearYEnviarRc($empresa, $ventasNuevas, $ventasBaja, $fecha, deleteOnError: false);
    }

    /**
     * Genera y envía UN SOLO RC con las boletas pendientes dentro del rango de fechas indicado
     * (usado por el comando automatizado de tareas programadas).
     *
     * @param int $rangoOffset  Cuántos días hacia atrás buscar boletas.
     *                          -1 = solo ayer, -7 = última semana, etc.
     *                          El límite superior siempre es ayer.
     *
     * Diferencia clave vs enviarResumenRc():
     *  - Filtra boletas por rango de fechas (no por un día exacto)
     *  - Si la API devuelve error, elimina el RC creado (las boletas no cambian de estado)
     */
    public function enviarResumenRcTodo(Empresa $empresa, Carbon $fechaRc, int $rangoOffset = -1): array
    {
        if (! $empresa->tieneFacturacionElectronica()) {
            return ['ok' => false, 'mensaje' => 'Empresa sin configuración de facturación electrónica.'];
        }

        $fechaHasta = $fechaRc->toDateString();
        // offset -1 = solo hoy; offset -7 = últimos 7 días hasta hoy
        $fechaDesde = ($rangoOffset === -1)
            ? $fechaHasta
            : $fechaRc->copy()->addDays($rangoOffset)->toDateString();

        $ventasNuevas = Venta::where('empresa_id', $empresa->id)
            ->with('serie')
            ->whereHas('serie', fn ($q) => $q->where('tipo', TipoComprobante::Boleta->value))
            ->where('estado_sunat', EstadoSunat::PorEnviar->value)
            ->where('estado', '!=', EstadoVenta::Anulada->value)
            ->whereNull('resumen_sunat_id')
            ->whereDate('fecha_emision', '>=', $fechaDesde)
            ->whereDate('fecha_emision', '<=', $fechaHasta)
            ->get();

        $ventasBaja = Venta::where('empresa_id', $empresa->id)
            ->with('serie')
            ->whereHas('serie', fn ($q) => $q->where('tipo', TipoComprobante::Boleta->value))
            ->where('estado_sunat', EstadoSunat::PorDarBaja->value)
            ->whereNull('resumen_sunat_id')
            ->whereDate('fecha_emision', '>=', $fechaDesde)
            ->whereDate('fecha_emision', '<=', $fechaHasta)
            ->get();

        $ventas = $ventasNuevas->merge($ventasBaja);

        if ($ventas->isEmpty()) {
            return ['ok' => false, 'vacio' => true, 'mensaje' => "Sin boletas pendientes en el rango ({$fechaDesde} → {$fechaHasta})."];
        }

        return $this->crearYEnviarRc($empresa, $ventasNuevas, $ventasBaja, $fechaRc, deleteOnError: true);
    }

    /**
     * Lógica común de creación y envío del RC.
     *
     * @param bool $deleteOnError  true = elimina el RC si la API falla (automatizado)
     *                             false = marca el RC como Error (página manual)
     */
    private function crearYEnviarRc(
        Empresa $empresa,
        \Illuminate\Support\Collection $ventasNuevas,
        \Illuminate\Support\Collection $ventasBaja,
        Carbon $fechaRc,
        bool $deleteOnError
    ): array {
        $existing    = ResumenSunat::where('empresa_id', $empresa->id)
            ->whereIn('tipo', ['diario', 'notas_diario'])
            ->whereDate('fecha_referencia', $fechaRc)
            ->count();
        $nro         = str_pad((string) ($existing + 1), 3, '0', STR_PAD_LEFT);
        $correlativo = 'RC-' . $fechaRc->format('Ymd') . '-' . $nro;

        $resumen = ResumenSunat::create([
            'empresa_id'       => $empresa->id,
            'tipo'             => 'diario',
            'fecha_referencia' => $fechaRc->toDateString(),
            'correlativo'      => $correlativo,
            'estado_sunat'     => EstadoSunat::Pendiente->value,
        ]);

        $ventas = $ventasNuevas->merge($ventasBaja);

        try {
            $response = $this->facturador->enviarResumen($resumen, $ventas);

            if ($response->ok) {
                $pathXml = null;
                if ($response->xmlBase64) {
                    $pathXml = "empresas/{$empresa->id}/resumenes/{$correlativo}.xml";
                    Storage::disk('local')->put($pathXml, base64_decode($response->xmlBase64));
                }

                $resumen->update([
                    'ticket_sunat' => $response->ticket,
                    'hash'         => $response->hash,
                    'path_xml'     => $pathXml,
                    'estado_sunat' => EstadoSunat::Enviado->value,
                    'fecha_envio'  => now(),
                ]);

                $idsNuevas = $ventasNuevas->pluck('id');
                $idsBaja   = $ventasBaja->pluck('id');

                if ($idsNuevas->isNotEmpty()) {
                    Venta::whereIn('id', $idsNuevas)->update([
                        'resumen_sunat_id' => $resumen->id,
                        'estado_sunat'     => EstadoSunat::EnResumen->value,
                    ]);
                }
                if ($idsBaja->isNotEmpty()) {
                    Venta::whereIn('id', $idsBaja)->update([
                        'resumen_sunat_id' => $resumen->id,
                    ]);
                }

                $texto = $ventasNuevas->count() . ' boleta(s)';
                if ($ventasBaja->count() > 0) {
                    $texto .= ' + ' . $ventasBaja->count() . ' baja(s)';
                }

                return [
                    'ok'          => true,
                    'resumen'     => $resumen,
                    'correlativo' => $correlativo,
                    'ticket'      => $response->ticket,
                    'mensaje'     => "RC {$correlativo} enviado ({$texto}). Ticket SUNAT: {$response->ticket}",
                ];
            }

            // API devolvió error
            if ($deleteOnError) {
                $resumen->delete();
            } else {
                $resumen->update([
                    'estado_sunat' => EstadoSunat::Error->value,
                    'sunat_error'  => $response->mensajeError(),
                    'fecha_envio'  => now(),
                ]);
            }

            return ['ok' => false, 'mensaje' => "Error al enviar {$correlativo}: " . $response->mensajeError()];

        } catch (\Throwable $e) {
            if ($deleteOnError) {
                $resumen->delete();
            } else {
                $resumen->update([
                    'estado_sunat' => EstadoSunat::Error->value,
                    'sunat_error'  => $e->getMessage(),
                ]);
            }

            return ['ok' => false, 'mensaje' => 'Excepción al enviar RC: ' . $e->getMessage()];
        }
    }

    /**
     * Consulta el estado de un RC en SUNAT.
     * Retorna array con: ok (bool), pending (bool), mensaje (string)
     */
    public function consultarEstadoRc(Empresa $empresa, ResumenSunat $resumen): array
    {
        $empresa->loadMissing('facturacion');
        $config = $empresa->facturacion;

        if (! $config) {
            return ['ok' => false, 'pending' => false, 'mensaje' => 'Sin configuración de facturación electrónica.'];
        }

        try {
            $response = $this->facturador->consultarEstadoResumen($config, $resumen->ticket_sunat);

            if ($response->ok) {
                $pathCdr = null;
                if ($response->cdrZip) {
                    $pathCdr = "empresas/{$empresa->id}/resumenes/{$resumen->correlativo}-CDR.zip";
                    Storage::disk('local')->put($pathCdr, base64_decode($response->cdrZip));
                }

                $resumen->update([
                    'sunat_success'     => true,
                    'sunat_codigo'      => $response->sunatCode,
                    'sunat_descripcion' => $response->sunatDescription,
                    'sunat_notas'       => $response->sunatNotes ? json_encode($response->sunatNotes) : null,
                    'path_cdr_zip'      => $pathCdr,
                    'estado_sunat'      => EstadoSunat::Aceptado->value,
                    'fecha_respuesta'   => now(),
                ]);

                Venta::where('resumen_sunat_id', $resumen->id)
                    ->where('estado_sunat', EstadoSunat::EnResumen->value)
                    ->update(['estado_sunat' => EstadoSunat::Aceptado->value]);

                Venta::where('resumen_sunat_id', $resumen->id)
                    ->where('estado_sunat', EstadoSunat::PorDarBaja->value)
                    ->update(['estado_sunat' => EstadoSunat::DadoDeBaja->value]);

                Nota::where('resumen_sunat_id', $resumen->id)
                    ->where('estado_sunat', EstadoSunat::PorDarBaja->value)
                    ->update(['estado_sunat' => EstadoSunat::DadoDeBaja->value]);

                return [
                    'ok'      => true,
                    'pending' => false,
                    'mensaje' => "[{$response->sunatCode}] {$response->sunatDescription}",
                ];
            }

            if ($response->httpError) {
                return ['ok' => false, 'pending' => false, 'mensaje' => "Error de conexión: {$response->httpError}"];
            }

            if ($response->pending) {
                return ['ok' => false, 'pending' => true, 'mensaje' => 'SUNAT aún procesando el RC (código 98).'];
            }

            $resumen->update([
                'sunat_success'     => false,
                'sunat_codigo'      => $response->sunatCode ?? $response->errorCode,
                'sunat_descripcion' => $response->sunatDescription ?? $response->errorMessage,
                'estado_sunat'      => EstadoSunat::Rechazado->value,
                'fecha_respuesta'   => now(),
            ]);

            return ['ok' => false, 'pending' => false, 'mensaje' => 'SUNAT rechazó el RC: ' . $response->mensajeError()];

        } catch (\Throwable $e) {
            return ['ok' => false, 'pending' => false, 'mensaje' => 'Excepción al consultar estado: ' . $e->getMessage()];
        }
    }
}

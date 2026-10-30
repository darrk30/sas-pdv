<?php

namespace App\Console\Commands;

use App\Enums\EstadoSunat;
use App\Models\Empresa;
use App\Models\ResumenSunat;
use App\Models\TareaProgramadaPdv;
use App\Services\ResumenBoletasService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PdvProcesarRcCommand extends Command
{
    protected $signature = 'pdv:procesar-rc
                            {empresa_id : ID de la empresa}
                            {--offset=-7 : Cuántos días hacia atrás buscar boletas (-1=ayer, -7=semana)}
                            {--tarea-id=0 : ID de TareaProgramadaPdv para registrar el resultado}';

    protected $description = 'Procesa el envío automático de boletas a SUNAT: retoma RCs pendientes y crea uno nuevo con las boletas del rango configurado';


    public function handle(ResumenBoletasService $service): int
    {
        $empresaId   = (int) $this->argument('empresa_id');
        $rangoOffset = (int) $this->option('offset');
        $tareaId     = (int) $this->option('tarea-id');

        $empresa = Empresa::find($empresaId);
        if (! $empresa) {
            $msg = "Error: empresa #{$empresaId} no encontrada.";
            $this->error($msg);
            $this->registrarResultado($tareaId, $msg);
            return self::FAILURE;
        }

        $this->line("Empresa: <{$empresa->name}> | rango offset: {$rangoOffset} días");

        $partes = [];

        // ── Paso 1: Retomar RCs que quedaron en estado "Enviado" (sin confirmar) ─
        $partes = array_merge($partes, $this->consultarRcsPendientes($service, $empresa));

        // ── Paso 2: Crear UN solo RC con las boletas del rango configurado ──────
        $partes = array_merge($partes, $this->crearNuevoRc($service, $empresa, $rangoOffset));

        $resumenFinal = empty($partes) ? 'Nada pendiente.' : implode(' | ', $partes);
        $this->line($resumenFinal);
        $this->registrarResultado($tareaId, $resumenFinal);

        return self::SUCCESS;
    }

    /**
     * Paso 1: Busca RCs en estado "Enviado" (tienen ticket pero aún no confirmados)
     * e intenta consultar su estado en SUNAT.
     */
    private function consultarRcsPendientes(ResumenBoletasService $service, Empresa $empresa): array
    {
        $resumenes = ResumenSunat::where('empresa_id', $empresa->id)
            ->where('estado_sunat', EstadoSunat::Enviado->value)
            ->whereNotNull('ticket_sunat')
            ->whereIn('tipo', ['diario', 'notas_diario'])
            ->orderBy('fecha_referencia')
            ->get();

        if ($resumenes->isEmpty()) {
            return [];
        }

        $this->line("Consultando {$resumenes->count()} RC(s) enviados pendientes de confirmación...");

        $resultados = [];

        foreach ($resumenes as $resumen) {
            $this->line("  Consultando {$resumen->correlativo} (ticket: {$resumen->ticket_sunat})...");
            $consulta = $service->consultarEstadoRc($empresa, $resumen);

            if ($consulta['ok']) {
                $this->info("  ✓ {$resumen->correlativo} — aceptado");
                $resultados[] = "{$resumen->correlativo}: aceptado";
            } elseif ($consulta['pending']) {
                $this->line("  ~ {$resumen->correlativo} — SUNAT aún procesando, se reintentará mañana");
                $resultados[] = "{$resumen->correlativo}: aún en proceso SUNAT";
            } else {
                $this->warn("  ✗ {$resumen->correlativo} — {$consulta['mensaje']}");
                $resultados[] = "{$resumen->correlativo}: {$consulta['mensaje']}";
            }
        }

        return $resultados;
    }

    /**
     * Paso 2: Crea UN RC con todas las boletas que aún no tienen resumen_sunat_id,
     * luego espera 90s y consulta el estado.
     * Si la API falla → el RC se elimina y las boletas no cambian de estado.
     * Si la consulta queda pendiente → el RC queda como "Enviado" y se retomará mañana (Paso 1).
     */
    private function crearNuevoRc(ResumenBoletasService $service, Empresa $empresa, int $rangoOffset): array
    {
        $fechaRc   = Carbon::today();
        $resultado = $service->enviarResumenRcTodo($empresa, $fechaRc, $rangoOffset);

        if (isset($resultado['vacio'])) {
            $this->line('Sin boletas pendientes para incluir en nuevo RC.');
            return [];
        }

        if (! $resultado['ok']) {
            $this->warn("Error al crear RC: {$resultado['mensaje']}");
            return ["Error nuevo RC: {$resultado['mensaje']}"];
        }

        $resumen = $resultado['resumen'];
        $this->info("✓ Nuevo RC enviado: {$resultado['mensaje']}");

        $this->line('Consultando estado del ticket...');
        $consulta = $service->consultarEstadoRc($empresa, $resumen);

        if ($consulta['ok']) {
            $this->info("✓ SUNAT aceptó {$resumen->correlativo}");
            return ["{$resultado['mensaje']} | SUNAT: aceptado"];
        }

        if ($consulta['pending']) {
            $this->line("  {$resumen->correlativo} — SUNAT aún procesando, se confirmará en la próxima ejecución.");
            return ["{$resultado['mensaje']} | SUNAT: procesando (se retoma en la próxima ejecución)"];
        }

        $this->warn("  {$resumen->correlativo} — {$consulta['mensaje']}");
        return ["{$resultado['mensaje']} | Consulta: {$consulta['mensaje']}"];
    }

    private function registrarResultado(int $tareaId, string $resultado): void
    {
        if ($tareaId <= 0) {
            return;
        }

        TareaProgramadaPdv::where('id', $tareaId)->update([
            'ultima_ejecucion' => now(),
            'ultimo_resultado' => $resultado,
        ]);
    }
}

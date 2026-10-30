<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Artisan;

class TareaProgramadaPdv extends Model
{
    protected $table = 'tareas_programadas_pdv';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'tipo',
        'hora',
        'dias_semana',
        'fecha_offset',
        'activo',
        'ultima_ejecucion',
        'ultimo_resultado',
    ];

    protected function casts(): array
    {
        return [
            'activo'           => 'boolean',
            'dias_semana'      => 'array',
            'fecha_offset'     => 'integer',
            'ultima_ejecucion' => 'datetime',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** Etiqueta legible del tipo de tarea. */
    public function getTipoLabelAttribute(): string
    {
        return match ($this->tipo) {
            'envio_boletas_rc' => 'Envío de Boletas RC',
            'reenvio_facturas' => 'Reenvío de Facturas',
            default            => ucfirst($this->tipo),
        };
    }

    /** Expresión cron derivada de hora y dias_semana. */
    public function getCronExpression(): string
    {
        [$hour, $minute] = explode(':', $this->hora);
        $dias = empty($this->dias_semana) ? '*' : implode(',', $this->dias_semana);

        return "{$minute} {$hour} * * {$dias}";
    }

    /** Ejecuta la tarea (llamado desde la cola o la acción de tabla). */
    public function ejecutar(): void
    {
        try {
            if ($this->tipo === 'reenvio_facturas') {
                $exitCode  = Artisan::call('pdv:reenviar-facturas', ['empresa_id' => $this->empresa_id]);
                $salida    = trim(Artisan::output());
                $resultado = $exitCode === 0
                    ? ($salida ?: 'Ejecutado correctamente.')
                    : "Error (código {$exitCode}): " . ($salida ?: 'sin detalle.');

                $this->update(['ultima_ejecucion' => now(), 'ultimo_resultado' => $resultado]);
            } else {
                // pdv:procesar-rc guarda su propio resultado limpio vía --tarea-id / registrarResultado.
                // No usar Artisan::output() aquí porque incluye todos los $this->line() intermedios.
                Artisan::call('pdv:procesar-rc', [
                    'empresa_id' => $this->empresa_id,
                    '--offset'   => $this->fecha_offset,
                    '--tarea-id' => $this->id,
                ]);
            }
        } catch (\Throwable $e) {
            $this->update(['ultima_ejecucion' => now(), 'ultimo_resultado' => 'Excepción: ' . $e->getMessage()]);
        }
    }
}

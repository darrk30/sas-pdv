<?php

namespace App\Console\Commands;

use App\Enums\EstadoGeneral;
use App\Models\Suscripcion;
use App\Models\TareaProgramada;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VencerSuscripcionesCommand extends Command
{
    protected $signature   = 'suscripciones:vencer';
    protected $description = 'Marca suscripciones vencidas como inactivas y alerta las próximas a vencer';

    // Días de anticipación para mostrar la alerta
    private const DIAS_ALERTA = 3;

    public function handle(): int
    {
        $hoy    = now()->toDateString();
        $limite = now()->addDays(self::DIAS_ALERTA)->toDateString();

        // ── 1. Marcar vencidas ────────────────────────────────────────────────
        $vencidas = Suscripcion::query()
            ->where('estado', EstadoGeneral::Activo)
            ->whereDate('fecha_fin', '<', $hoy)
            ->with('empresa')
            ->get();

        foreach ($vencidas as $suscripcion) {
            $suscripcion->update(['estado' => EstadoGeneral::Inactivo]);

            if ($suscripcion->empresa) {
                $suscripcion->empresa->update([
                    'estado'                        => 'inactivo',
                    'suscripcion_proxima_a_vencer'  => false,
                ]);
            }
        }

        $this->info("Suscripciones vencidas: {$vencidas->count()}.");

        // ── 2. Marcar próximas a vencer (entre hoy y DIAS_ALERTA días) ────────
        $proximasAVencer = Suscripcion::query()
            ->where('estado', EstadoGeneral::Activo)
            ->whereDate('fecha_fin', '>=', $hoy)
            ->whereDate('fecha_fin', '<=', $limite)
            ->with('empresa')
            ->get();

        $superAdmins = $this->getSuperAdmins();

        foreach ($proximasAVencer as $suscripcion) {
            if ($suscripcion->empresa && ! $suscripcion->empresa->suscripcion_proxima_a_vencer) {
                $suscripcion->empresa->update(['suscripcion_proxima_a_vencer' => true]);

                $dias     = (int) now()->startOfDay()->diffInDays($suscripcion->fecha_fin->startOfDay(), false);
                $diasText = $dias <= 0 ? 'hoy' : "en {$dias} día" . ($dias === 1 ? '' : 's');

                foreach ($superAdmins as $admin) {
                    Notification::make()
                        ->warning()
                        ->title('Suscripción próxima a vencer')
                        ->body("La empresa **{$suscripcion->empresa->name}** vence {$diasText}. Verifica si hay pago registrado.")
                        ->sendToDatabase($admin);
                }
            }
        }

        $this->info("Suscripciones próximas a vencer: {$proximasAVencer->count()}.");

        // ── 3. Limpiar alerta de empresas cuya suscripción ya no está próxima ─
        $sinAlerta = Suscripcion::query()
            ->where('estado', EstadoGeneral::Activo)
            ->whereDate('fecha_fin', '>', $limite)
            ->with('empresa')
            ->get();

        foreach ($sinAlerta as $suscripcion) {
            if ($suscripcion->empresa?->suscripcion_proxima_a_vencer) {
                $suscripcion->empresa->update(['suscripcion_proxima_a_vencer' => false]);
            }
        }

        TareaProgramada::where('comando', $this->signature)->update(['ultima_ejecucion' => now()]);

        return self::SUCCESS;
    }

    private function getSuperAdmins(): \Illuminate\Support\Collection
    {
        $ids = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereNull('model_has_roles.empresa_id')
            ->where('roles.name', 'Super Administrador')
            ->whereNull('roles.empresa_id')
            ->pluck('model_has_roles.model_id');

        return User::whereIn('id', $ids)->get();
    }
}

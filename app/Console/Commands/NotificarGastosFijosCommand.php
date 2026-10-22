<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\GastoFijo;
use App\Models\User;
use App\Notifications\GastoFijoVenceNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

class NotificarGastosFijosCommand extends Command
{
    protected $signature   = 'gastos-fijos:notificar';
    protected $description = 'Notifica gastos fijos que vencen pronto según sus días de aviso';

    public function handle(): int
    {
        $hoy = now()->toDateString();

        // Solo empresas con módulo de gastos activo
        $empresas = Empresa::whereJsonContains('modulos_activos->gastos', true)
            ->get(['id', 'slug']);

        $notificadas = 0;

        foreach ($empresas as $empresa) {
            $gastos = GastoFijo::where('empresa_id', $empresa->id)
                ->where('estado', 'activo')
                ->whereNotNull('proxima_fecha_pago')
                ->get()
                ->filter(fn (GastoFijo $g) => $g->venceProximo());

            if ($gastos->isEmpty()) continue;

            // Administradores de la empresa
            app(PermissionRegistrar::class)->setPermissionsTeamId($empresa->id);
            $admins = User::whereHas('empresas', fn ($q) => $q->where('empresas.id', $empresa->id))
                ->whereHas('roles', fn ($q) => $q->where('roles.name', 'Administrador'))
                ->get();

            if ($admins->isEmpty()) continue;

            $url = route('filament.pdv.resources.gastos-fijos.index', ['tenant' => $empresa->slug]);

            foreach ($gastos as $gasto) {
                $cacheKey = "gasto_fijo_aviso_{$empresa->id}_{$gasto->id}_{$hoy}";

                if (Cache::has($cacheKey)) continue;

                $notificacion = new GastoFijoVenceNotification(
                    empresaId: $empresa->id,
                    nombre:    $gasto->nombre,
                    monto:     (float) $gasto->monto,
                    fecha:     $gasto->proxima_fecha_pago->format('d/m/Y'),
                    url:       $url,
                );

                foreach ($admins as $admin) {
                    $admin->notify($notificacion);
                }

                Cache::put($cacheKey, true, now()->endOfDay());
                $notificadas++;
            }
        }

        $this->info("Notificaciones de gastos fijos enviadas: {$notificadas}.");
        return self::SUCCESS;
    }
}

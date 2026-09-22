<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\User;
use App\Models\Venta;
use App\Notifications\CreditoVencidoNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

class AlertarCreditosVencidosCommand extends Command
{
    protected $signature   = 'creditos:alertar-vencidos';
    protected $description = 'Notifica créditos que vencen hoy y los que vencieron ayer sin cobrar';

    // Tipos de alerta
    private const HOY      = 'hoy';
    private const VENCIDO  = 'vencido';

    public function handle(): int
    {
        $hoy  = now()->toDateString();
        $ayer = now()->subDay()->toDateString();

        $ventas = Venta::query()
            ->with(['serie:id,serie'])
            ->whereNotNull('fecha_vencimiento')
            ->where(fn ($q) => $q
                ->whereDate('fecha_vencimiento', $hoy)
                ->orWhereDate('fecha_vencimiento', $ayer)
            )
            ->where('saldo_pendiente', '>', 0)
            ->select([
                'id', 'empresa_id', 'cliente_id', 'cliente_nombre',
                'serie_id', 'correlativo', 'saldo_pendiente', 'fecha_vencimiento', 'vendedor_id',
            ])
            ->orderBy('empresa_id')
            ->orderBy('cliente_id')
            ->get();

        if ($ventas->isEmpty()) {
            $this->info('Sin créditos para notificar hoy.');
            return self::SUCCESS;
        }

        $notificadas = 0;

        foreach ($ventas->groupBy('empresa_id') as $empresaId => $ventasEmpresa) {
            $empresa = Empresa::find($empresaId);
            if (! $empresa) continue;

            // Administradores de la empresa (requiere team context de Spatie)
            app(PermissionRegistrar::class)->setPermissionsTeamId($empresaId);
            $admins = User::whereHas('empresas', fn ($q) => $q->where('empresas.id', $empresaId))
                ->whereHas('roles', fn ($q) => $q->where('roles.name', 'Administrador'))
                ->pluck('id');

            // Vendedores que registraron alguna de las ventas vencidas de esta empresa
            $vendedoresIds = $ventasEmpresa->pluck('vendedor_id')->filter()->unique();

            $usuarioIds = $admins->merge($vendedoresIds)->unique();
            $usuarios   = User::whereIn('id', $usuarioIds)->get();

            if ($usuarios->isEmpty()) continue;

            foreach ($ventasEmpresa->groupBy(fn ($v) => $v->cliente_id ?? 'sc_' . $v->id) as $clienteKey => $ventasCliente) {
                $primeraVenta  = $ventasCliente->first();
                $clienteNombre = $primeraVenta->cliente_nombre ?: 'Cliente general';
                $clienteId     = $primeraVenta->cliente_id;

                // Separar: vencen hoy vs vencieron ayer
                $vencenHoy    = $ventasCliente->filter(fn ($v) => $v->fecha_vencimiento->toDateString() === $hoy);
                $vencieronAyer = $ventasCliente->filter(fn ($v) => $v->fecha_vencimiento->toDateString() === $ayer);

                $url = route('filament.pdv.pages.cuentas-por-cobrar-page', ['tenant' => $empresa->slug]);
                if ($clienteId) {
                    $url .= '?filtroClienteId=' . $clienteId . '&filtroClienteNombre=' . urlencode($clienteNombre);
                }

                // ── Caso 1: vence HOY ─────────────────────────────────────────
                if ($vencenHoy->isNotEmpty()) {
                    $cacheKey = "credito_hoy_{$empresaId}_{$clienteKey}_{$hoy}";

                    if (! Cache::has($cacheKey)) {
                        $lineas = $vencenHoy->map(fn (Venta $v) =>
                            '• **' . $this->comprobante($v) . '** — saldo: **S/ ' . number_format((float) $v->saldo_pendiente, 2) . '**'
                        )->implode("\n");

                        $count  = $vencenHoy->count();
                        $titulo = $count === 1
                            ? "⏰ Pago vence HOY — {$clienteNombre}"
                            : "⏰ {$count} pagos vencen HOY — {$clienteNombre}";

                        $cuerpo = ($count === 1
                            ? "El cliente **{$clienteNombre}** tiene un crédito que vence **hoy**:"
                            : "El cliente **{$clienteNombre}** tiene {$count} créditos que vencen **hoy**:")
                            . "\n\n{$lineas}\n\n"
                            . 'Recuerda contactar al cliente para gestionar el cobro.';

                        $this->enviar($usuarios, 'warning', $titulo, $cuerpo, $url, "ver_hoy_{$clienteKey}", $empresaId);
                        Cache::put($cacheKey, true, now()->endOfDay());
                        $notificadas++;
                    }
                }

                // ── Caso 2: venció AYER (1 día de retraso) ───────────────────
                if ($vencieronAyer->isNotEmpty()) {
                    $cacheKey = "credito_venc1_{$empresaId}_{$clienteKey}_{$hoy}";

                    if (! Cache::has($cacheKey)) {
                        $lineas = $vencieronAyer->map(fn (Venta $v) =>
                            '• **' . $this->comprobante($v) . '** — saldo: **S/ ' . number_format((float) $v->saldo_pendiente, 2) . '** — venció el ' . $v->fecha_vencimiento->format('d/m/Y')
                        )->implode("\n");

                        $count  = $vencieronAyer->count();
                        $titulo = $count === 1
                            ? "🔴 Crédito vencido — {$clienteNombre}"
                            : "🔴 {$count} créditos vencidos — {$clienteNombre}";

                        $cuerpo = ($count === 1
                            ? "El cliente **{$clienteNombre}** se pasó de la fecha de vencimiento y aún tiene saldo pendiente:"
                            : "El cliente **{$clienteNombre}** tiene {$count} créditos vencidos con saldo pendiente:")
                            . "\n\n{$lineas}\n\n"
                            . 'Gestiona el cobro a la brevedad posible.';

                        $this->enviar($usuarios, 'danger', $titulo, $cuerpo, $url, "ver_venc_{$clienteKey}", $empresaId);
                        Cache::put($cacheKey, true, now()->endOfDay());
                        $notificadas++;
                    }
                }
            }
        }

        $this->info("Notificaciones enviadas: {$notificadas}.");
        return self::SUCCESS;
    }

    private function comprobante(Venta $v): string
    {
        $serie  = $v->serie?->serie ?? '???';
        $numero = str_pad($v->correlativo, 8, '0', STR_PAD_LEFT);
        return "{$serie}-{$numero}";
    }

    private function enviar(
        \Illuminate\Support\Collection $usuarios,
        string $color,
        string $titulo,
        string $cuerpo,
        string $url,
        string $actionKey,
        int $empresaId
    ): void {
        $notificacion = new CreditoVencidoNotification(
            empresaId:  $empresaId,
            titulo:     $titulo,
            cuerpo:     $cuerpo,
            url:        $url,
            actionKey:  $actionKey,
            color:      $color,
        );

        foreach ($usuarios as $usuario) {
            $usuario->notify($notificacion);
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Promocion;
use App\Models\TareaProgramada;
use Illuminate\Console\Command;

class ResetUsosPromocionesCommand extends Command
{
    protected $signature   = 'promociones:reset-usos';
    protected $description = 'Resetea los usos_actuales de todas las promociones a 0 (ejecutar diariamente a medianoche)';

    public function handle(): int
    {
        $updated = Promocion::query()->update(['usos_actuales' => 0]);
        $this->info("Usos reseteados en {$updated} promoción(es).");

        TareaProgramada::where('comando', $this->signature)->update(['ultima_ejecucion' => now()]);

        return self::SUCCESS;
    }
}

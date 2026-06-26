<?php

namespace App\Observers;

use App\Models\Empresa;
use Database\Seeders\CajaPrincipalSeeder;
use Database\Seeders\ClienteGeneralSeeder;
use Database\Seeders\ConfiguracionInicialSeeder;
use Database\Seeders\DimensionSeeder;
use Database\Seeders\MetodoPagoSeeder;
use Database\Seeders\ProveedorGeneralSeeder;
use Database\Seeders\SeriesSeeder;
use Database\Seeders\TurnoSeeder;

class EmpresaObserver
{
    
    /**
     * Handle the Empresa "created" event.
     */
    public function created(Empresa $empresa): void
    {
        app()->instance('bypass_tenant_scope', true);

        try {
            (new DimensionSeeder())->runForEmpresa($empresa);
            (new ConfiguracionInicialSeeder())->runForEmpresa($empresa);
            (new ProveedorGeneralSeeder())->runForEmpresa($empresa);
            (new MetodoPagoSeeder())->runForEmpresa($empresa);
            (new TurnoSeeder())->runForEmpresa($empresa);
            (new CajaPrincipalSeeder())->runForEmpresa($empresa);
            (new ClienteGeneralSeeder())->runForEmpresa($empresa);
            (new SeriesSeeder())->runForEmpresa($empresa);
        } finally {
            app()->forgetInstance('bypass_tenant_scope');
        }
    }

    /**
     * Handle the Empresa "updated" event.
     */
    public function updated(Empresa $empresa): void
    {
        //
    }

    /**
     * Handle the Empresa "deleted" event.
     */
    public function deleted(Empresa $empresa): void
    {
        //
    }

    /**
     * Handle the Empresa "restored" event.
     */
    public function restored(Empresa $empresa): void
    {
        //
    }

    /**
     * Handle the Empresa "force deleted" event.
     */
    public function forceDeleted(Empresa $empresa): void
    {
        //
    }
}

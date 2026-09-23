<?php

namespace App\Observers;

use App\Models\Empresa;
use App\Models\Role;
use Illuminate\Support\Facades\Storage;
use Database\Seeders\CajaPrincipalSeeder;
use Database\Seeders\ClienteGeneralSeeder;
use Database\Seeders\ConfiguracionInicialSeeder;
use Database\Seeders\DimensionSeeder;
use Database\Seeders\MetodoPagoSeeder;
use Database\Seeders\ProductosPruebaSeeder;
use Database\Seeders\ProveedorGeneralSeeder;
use Database\Seeders\RolesEmpresaSeeder;
use Database\Seeders\SeriesSeeder;
use Database\Seeders\TurnoSeeder;

class EmpresaObserver
{
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
            (new RolesEmpresaSeeder())->runForEmpresa($empresa);
            // (new ProductosPruebaSeeder())->runForEmpresa($empresa);
        } finally {
            app()->forgetInstance('bypass_tenant_scope');
        }
    }

    public function updating(Empresa $empresa): void
    {
        if ($empresa->isDirty('logo') && ($old = $empresa->getOriginal('logo'))) {
            Storage::disk('public')->delete($old);
        }
    }

    public function updated(Empresa $empresa): void
    {
        // Invalida la cache de config de impresión si cambiaron campos relevantes
        if ($empresa->isDirty(['impresion_comprobante_directo', 'api_token_impresion'])) {
            $empresa->invalidarCacheImpresion();
        }
    }

    public function deleted(Empresa $empresa): void
    {
        // Borra roles de la empresa → cascada elimina model_has_roles y role_has_permissions
        Role::where('empresa_id', $empresa->id)->delete();

        if ($empresa->logo) {
            Storage::disk('public')->delete($empresa->logo);
        }
    }

    public function restored(Empresa $empresa): void {}

    public function forceDeleted(Empresa $empresa): void {}
}

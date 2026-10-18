<?php

namespace App\Livewire\Admin;

use App\Filament\Resources\Empresas\EmpresaResource;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

class EliminarEmpresaModal extends Component
{
    #[Locked]
    public int $empresaId;

    #[Locked]
    public string $nombreEmpresa;

    #[Locked]
    public string $indexUrl = '';

    public string $confirmacion = '';
    public string $estado = 'confirmando'; // confirmando | completado | error
    public string $mensajeError = '';

    public static array $pasos = [
        'Roles y permisos',
        'Kardex',
        'Ventas',
        'Órdenes',
        'Sesiones de caja',
        'Inventarios',
        'Recetas',
        'Productos',
        'Promociones',
        'Compras y ajustes',
        'Clientes y cupones',
        'Catálogo y configuración',
        'Empresa',
    ];

    public function mount(int $empresaId): void
    {
        $this->empresaId     = $empresaId;
        $this->nombreEmpresa = DB::table('empresas')->where('id', $empresaId)->value('name') ?? '';
        $this->indexUrl      = EmpresaResource::getUrl('index');
    }

    public function cancelar(): void
    {
        $this->dispatch('cancelar-empresa-eliminar');
    }

    public function confirmar(): void
    {
        if (trim($this->confirmacion) !== trim($this->nombreEmpresa)) {
            $this->addError('confirmacion', 'El nombre no coincide.');
            return;
        }

        try {
            DB::transaction(function (): void {
                $this->paso0();   // Roles y permisos
                $this->paso1();   // Kardex
                $this->paso2();   // Ventas
                $this->paso3();   // Órdenes
                $this->paso4();   // Sesiones de caja
                $this->paso5();   // Inventarios
                $this->paso6();   // Recetas
                $this->paso7();   // Productos
                $this->paso8();   // Promociones
                $this->paso9();   // Compras y ajustes
                $this->paso10();  // Clientes y cupones
                $this->paso11();  // Catálogo y configuración
                $this->paso12();  // Empresa
            });
        } catch (\Throwable $e) {
            $this->estado       = 'error';
            $this->mensajeError = $e->getMessage();
            return;
        }

        $this->estado = 'completado';
    }

    // ── Pasos individuales ────────────────────────────────────────────────────

    protected function paso0(): void
    {
        DB::table('model_has_permissions')->where('empresa_id', $this->empresaId)->delete();
        DB::table('model_has_roles')->where('empresa_id', $this->empresaId)->delete();
        DB::table('roles')->where('empresa_id', $this->empresaId)->delete();
    }

    protected function paso1(): void
    {
        DB::table('kardex')->where('empresa_id', $this->empresaId)->delete();
    }

    protected function paso2(): void
    {
        DB::table('ventas')->where('empresa_id', $this->empresaId)->delete();
    }

    protected function paso3(): void
    {
        DB::table('ordenes')->where('empresa_id', $this->empresaId)->delete();
    }

    protected function paso4(): void
    {
        $sesionIds = DB::table('sesion_cajas')->where('empresa_id', $this->empresaId)->pluck('id');
        if ($sesionIds->isNotEmpty()) {
            DB::table('sesion_caja_pagos')->whereIn('sesion_caja_id', $sesionIds)->delete();
        }
        DB::table('ingresos_egresos')->where('empresa_id', $this->empresaId)->delete();
        DB::table('transacciones')->where('empresa_id', $this->empresaId)->delete();
        DB::table('sesion_cajas')->where('empresa_id', $this->empresaId)->delete();
        DB::table('turnos')->where('empresa_id', $this->empresaId)->delete();
    }

    protected function paso5(): void
    {
        DB::table('inventarios')->where('empresa_id', $this->empresaId)->delete();
    }

    protected function paso6(): void
    {
        $ids = DB::table('recetas')->where('empresa_id', $this->empresaId)->pluck('id');
        if ($ids->isNotEmpty()) {
            DB::table('receta_detalles')->whereIn('receta_id', $ids)->delete();
        }
        DB::table('recetas')->where('empresa_id', $this->empresaId)->delete();
    }

    protected function paso7(): void
    {
        $productoIds = DB::table('productos')->where('empresa_id', $this->empresaId)->pluck('id');
        if ($productoIds->isNotEmpty()) {
            $varianteIds = DB::table('variantes')->whereIn('producto_id', $productoIds)->pluck('id');
            if ($varianteIds->isNotEmpty()) {
                DB::table('variante_valores')->whereIn('variante_id', $varianteIds)->delete();
            }
            DB::table('variantes')->whereIn('producto_id', $productoIds)->delete();
            DB::table('galeria_productos')->whereIn('producto_id', $productoIds)->delete();
            DB::table('lista_precio_producto')->whereIn('producto_id', $productoIds)->delete();

            $paIds = DB::table('producto_atributos')->whereIn('producto_id', $productoIds)->pluck('id');
            if ($paIds->isNotEmpty()) {
                DB::table('exclusions')->whereIn('producto_atributo_id', $paIds)->delete();
                DB::table('producto_atributo_valors')->whereIn('producto_atributo_id', $paIds)->delete();
                DB::table('producto_atributos')->whereIn('id', $paIds)->delete();
            }
        }
        DB::table('productos')->where('empresa_id', $this->empresaId)->delete();
    }

    protected function paso8(): void
    {
        $ids = DB::table('promociones')->where('empresa_id', $this->empresaId)->pluck('id');
        if ($ids->isNotEmpty()) {
            DB::table('promocion_detalles')->whereIn('promocion_id', $ids)->delete();
        }
        DB::table('promociones')->where('empresa_id', $this->empresaId)->delete();
    }

    protected function paso9(): void
    {
        DB::table('compras')->where('empresa_id', $this->empresaId)->delete();
        DB::table('ajustes')->where('empresa_id', $this->empresaId)->delete();
    }

    protected function paso10(): void
    {
        DB::table('lista_deseos')->where('empresa_id', $this->empresaId)->delete();
        DB::table('carritos')->where('empresa_id', $this->empresaId)->delete();
        DB::table('clientes')->where('empresa_id', $this->empresaId)->delete();

        $cuponIds = DB::table('cupones')->where('empresa_id', $this->empresaId)->pluck('id');
        if ($cuponIds->isNotEmpty()) {
            DB::table('cupon_usos')->whereIn('cupon_id', $cuponIds)->delete();
        }
        DB::table('cupones')->where('empresa_id', $this->empresaId)->delete();
    }

    protected function paso11(): void
    {
        DB::table('series')->where('empresa_id', $this->empresaId)->delete();
        DB::table('cajas')->where('empresa_id', $this->empresaId)->delete();
        DB::table('proveedores')->where('empresa_id', $this->empresaId)->delete();
        DB::table('metodos_pago')->where('empresa_id', $this->empresaId)->delete();
        DB::table('metodos_envio')->where('empresa_id', $this->empresaId)->delete();
        DB::table('categorias')->where('empresa_id', $this->empresaId)->delete();
        DB::table('marcas')->where('empresa_id', $this->empresaId)->delete();

        $atributoIds = DB::table('atributos')->where('empresa_id', $this->empresaId)->pluck('id');
        if ($atributoIds->isNotEmpty()) {
            DB::table('valors')->whereIn('atributo_id', $atributoIds)->delete();
        }
        DB::table('atributos')->where('empresa_id', $this->empresaId)->delete();

        $dimIds = DB::table('dimensions')->where('empresa_id', $this->empresaId)->pluck('id');
        if ($dimIds->isNotEmpty()) {
            DB::table('unidades_medidas')->whereIn('dimension_id', $dimIds)->delete();
        }
        DB::table('dimensions')->where('empresa_id', $this->empresaId)->delete();
        DB::table('listas_precios')->where('empresa_id', $this->empresaId)->delete();
        DB::table('gastos')->where('empresa_id', $this->empresaId)->delete();
        DB::table('gastos_fijos')->where('empresa_id', $this->empresaId)->delete();
        DB::table('produccions')->where('empresa_id', $this->empresaId)->delete();
        DB::table('mesas')->where('empresa_id', $this->empresaId)->delete();
        DB::table('pisos')->where('empresa_id', $this->empresaId)->delete();
        DB::table('impresoras')->where('empresa_id', $this->empresaId)->delete();
        DB::table('empresa_facturacion')->where('empresa_id', $this->empresaId)->delete();
        DB::table('resumenes_sunat')->where('empresa_id', $this->empresaId)->delete();
        DB::table('notas')->where('empresa_id', $this->empresaId)->delete();
        DB::table('push_subscriptions')->where('empresa_id', $this->empresaId)->delete();
        DB::table('empresa_user')->where('empresa_id', $this->empresaId)->delete();

        $suscIds = DB::table('suscripciones')->where('empresa_id', $this->empresaId)->pluck('id');
        if ($suscIds->isNotEmpty()) {
            DB::table('pagos_clientes')->whereIn('suscripcion_id', $suscIds)->delete();
        }
        DB::table('suscripciones')->where('empresa_id', $this->empresaId)->delete();
    }

    protected function paso12(): void
    {
        DB::table('empresas')->where('id', $this->empresaId)->delete();
    }

    public function render()
    {
        return view('livewire.admin.eliminar-empresa-modal');
    }
}

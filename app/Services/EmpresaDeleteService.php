<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class EmpresaDeleteService
{
    /**
     * Elimina toda la data de una empresa en el orden correcto para
     * respetar los FK constraints, luego elimina la empresa misma.
     */
    public function eliminar(int $empresaId): void
    {
        DB::transaction(function () use ($empresaId): void {

            // ── 1. Spatie: permisos y roles de esta empresa ──────────────────
            DB::table('model_has_permissions')->where('empresa_id', $empresaId)->delete();
            DB::table('model_has_roles')->where('empresa_id', $empresaId)->delete();
            DB::table('roles')->where('empresa_id', $empresaId)->delete();

            // ── 2. Kardex ────────────────────────────────────────────────────
            DB::table('kardex')->where('empresa_id', $empresaId)->delete();

            // ── 3. Ventas (venta_detalles y venta_pagos cascadean) ────────────
            DB::table('ventas')->where('empresa_id', $empresaId)->delete();

            // ── 4. Órdenes (orden_detalles cascadea) ─────────────────────────
            DB::table('ordenes')->where('empresa_id', $empresaId)->delete();

            // ── 5. Sesiones de caja y dependientes ───────────────────────────
            $sesionIds = DB::table('sesion_cajas')
                ->where('empresa_id', $empresaId)
                ->pluck('id');

            if ($sesionIds->isNotEmpty()) {
                DB::table('sesion_caja_pagos')->whereIn('sesion_caja_id', $sesionIds)->delete();
            }

            DB::table('ingresos_egresos')->where('empresa_id', $empresaId)->delete();
            DB::table('transacciones')->where('empresa_id', $empresaId)->delete();
            DB::table('sesion_cajas')->where('empresa_id', $empresaId)->delete();
            DB::table('turnos')->where('empresa_id', $empresaId)->delete();

            // ── 6. Inventarios ────────────────────────────────────────────────
            DB::table('inventarios')->where('empresa_id', $empresaId)->delete();

            // ── 7. Recetas y detalles ─────────────────────────────────────────
            $recetaIds = DB::table('recetas')
                ->where('empresa_id', $empresaId)
                ->pluck('id');

            if ($recetaIds->isNotEmpty()) {
                DB::table('receta_detalles')->whereIn('receta_id', $recetaIds)->delete();
            }

            DB::table('recetas')->where('empresa_id', $empresaId)->delete();

            // ── 8. Productos y toda su descendencia ───────────────────────────
            $productoIds = DB::table('productos')
                ->where('empresa_id', $empresaId)
                ->pluck('id');

            if ($productoIds->isNotEmpty()) {
                $varianteIds = DB::table('variantes')
                    ->whereIn('producto_id', $productoIds)
                    ->pluck('id');

                if ($varianteIds->isNotEmpty()) {
                    DB::table('variante_valores')->whereIn('variante_id', $varianteIds)->delete();
                }

                DB::table('variantes')->whereIn('producto_id', $productoIds)->delete();
                DB::table('galeria_productos')->whereIn('producto_id', $productoIds)->delete();
                DB::table('lista_precio_producto')->whereIn('producto_id', $productoIds)->delete();

                $paIds = DB::table('producto_atributos')
                    ->whereIn('producto_id', $productoIds)
                    ->pluck('id');

                if ($paIds->isNotEmpty()) {
                    DB::table('exclusions')->whereIn('producto_atributo_id', $paIds)->delete();
                    DB::table('producto_atributo_valors')->whereIn('producto_atributo_id', $paIds)->delete();
                    DB::table('producto_atributos')->whereIn('id', $paIds)->delete();
                }
            }

            DB::table('productos')->where('empresa_id', $empresaId)->delete();

            // ── 9. Promociones y detalles ─────────────────────────────────────
            $promoIds = DB::table('promociones')
                ->where('empresa_id', $empresaId)
                ->pluck('id');

            if ($promoIds->isNotEmpty()) {
                DB::table('promocion_detalles')->whereIn('promocion_id', $promoIds)->delete();
            }

            DB::table('promociones')->where('empresa_id', $empresaId)->delete();

            // ── 10. Compras (compra_detalles y compra_pagos cascadean) ─────────
            DB::table('compras')->where('empresa_id', $empresaId)->delete();

            // ── 11. Ajustes (ajuste_detalles cascadea) ────────────────────────
            DB::table('ajustes')->where('empresa_id', $empresaId)->delete();

            // ── 12. Clientes, carritos y lista de deseos ──────────────────────
            // carrito_items cascadea desde carritos
            DB::table('lista_deseos')->where('empresa_id', $empresaId)->delete();
            DB::table('carritos')->where('empresa_id', $empresaId)->delete();
            DB::table('clientes')->where('empresa_id', $empresaId)->delete();

            // ── 13. Cupones y usos ────────────────────────────────────────────
            $cuponIds = DB::table('cupones')
                ->where('empresa_id', $empresaId)
                ->pluck('id');

            if ($cuponIds->isNotEmpty()) {
                DB::table('cupon_usos')->whereIn('cupon_id', $cuponIds)->delete();
            }

            DB::table('cupones')->where('empresa_id', $empresaId)->delete();

            // ── 14. Catálogo operativo ────────────────────────────────────────
            DB::table('series')->where('empresa_id', $empresaId)->delete();
            DB::table('cajas')->where('empresa_id', $empresaId)->delete();
            DB::table('proveedores')->where('empresa_id', $empresaId)->delete();
            DB::table('metodos_pago')->where('empresa_id', $empresaId)->delete();
            DB::table('metodos_envio')->where('empresa_id', $empresaId)->delete();

            // ── 15. Catálogo de clasificación ─────────────────────────────────
            DB::table('categorias')->where('empresa_id', $empresaId)->delete();
            DB::table('marcas')->where('empresa_id', $empresaId)->delete();

            $atributoIds = DB::table('atributos')
                ->where('empresa_id', $empresaId)
                ->pluck('id');

            if ($atributoIds->isNotEmpty()) {
                DB::table('valors')->whereIn('atributo_id', $atributoIds)->delete();
            }

            DB::table('atributos')->where('empresa_id', $empresaId)->delete();

            $dimensionIds = DB::table('dimensions')
                ->where('empresa_id', $empresaId)
                ->pluck('id');

            if ($dimensionIds->isNotEmpty()) {
                DB::table('unidades_medidas')->whereIn('dimension_id', $dimensionIds)->delete();
            }

            DB::table('dimensions')->where('empresa_id', $empresaId)->delete();
            DB::table('listas_precios')->where('empresa_id', $empresaId)->delete();

            // ── 16. Finanzas y operaciones ─────────────────────────────────────
            DB::table('gastos')->where('empresa_id', $empresaId)->delete();
            DB::table('gastos_fijos')->where('empresa_id', $empresaId)->delete();
            DB::table('produccions')->where('empresa_id', $empresaId)->delete();

            // ── 17. Restaurante ───────────────────────────────────────────────
            DB::table('mesas')->where('empresa_id', $empresaId)->delete();
            DB::table('pisos')->where('empresa_id', $empresaId)->delete();

            // ── 18. Configuración de empresa ──────────────────────────────────
            DB::table('impresoras')->where('empresa_id', $empresaId)->delete();
            DB::table('empresa_facturacion')->where('empresa_id', $empresaId)->delete();
            DB::table('resumenes_sunat')->where('empresa_id', $empresaId)->delete();
            DB::table('notas')->where('empresa_id', $empresaId)->delete();
            DB::table('push_subscriptions')->where('empresa_id', $empresaId)->delete();

            // ── 19. Vínculos de usuarios ──────────────────────────────────────
            DB::table('empresa_user')->where('empresa_id', $empresaId)->delete();

            // ── 20. Suscripción y pagos ───────────────────────────────────────
            $suscripcionIds = DB::table('suscripciones')
                ->where('empresa_id', $empresaId)
                ->pluck('id');

            if ($suscripcionIds->isNotEmpty()) {
                DB::table('pagos_clientes')->whereIn('suscripcion_id', $suscripcionIds)->delete();
            }

            DB::table('suscripciones')->where('empresa_id', $empresaId)->delete();

            // ── 21. Empresa ───────────────────────────────────────────────────
            DB::table('empresas')->where('id', $empresaId)->delete();
        });
    }
}

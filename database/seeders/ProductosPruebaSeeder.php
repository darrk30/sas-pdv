<?php

namespace Database\Seeders;

use App\Enums\EstadoGeneral;
use App\Models\Empresa;
use App\Models\UnidadesMedida;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductosPruebaSeeder extends Seeder
{
    private const PREFIX = 'PT-TEST-';
    private const TOTAL  = 500;

    public function run(): void
    {
        $empresa = Empresa::first();

        if (! $empresa) {
            $this->command->error('No hay empresas registradas. Inicia sesión y crea una primero.');
            return;
        }

        $yaExisten = DB::table('productos')
            ->where('empresa_id', $empresa->id)
            ->where('codigo_interno', 'like', self::PREFIX . '%')
            ->count();

        if ($yaExisten > 0) {
            $this->command->warn("Ya existen {$yaExisten} productos de prueba para \"{$empresa->nombre}\". Usa --fresh para limpiarlos primero.");
            return;
        }

        $unidad = UnidadesMedida::where('empresa_id', $empresa->id)->first();

        if (! $unidad) {
            $this->command->error('La empresa no tiene unidades de medida configuradas.');
            return;
        }

        $ahora    = now();
        $factory  = new \Database\Factories\ProductoFactory();
        $productos = [];

        for ($i = 1; $i <= self::TOTAL; $i++) {
            $data   = $factory->definition();
            $codigo = self::PREFIX . str_pad($i, 3, '0', STR_PAD_LEFT);
            $slug   = 'pt-test-' . str_pad($i, 3, '0', STR_PAD_LEFT) . '-' . $empresa->id;

            $productos[] = [
                'empresa_id'       => $empresa->id,
                'unidad_medida_id' => $unidad->id,
                'nombre'           => $data['nombre'],
                'slug'             => $slug,
                'codigo_interno'   => $codigo,
                'precio_costo'     => $data['precio_costo'],
                'precio_venta'     => $data['precio_venta'],
                'estado'           => EstadoGeneral::Activo->value,
                'control_de_stock' => true,
                'venta_sin_stock'  => false,
                'es_cortesia'      => false,
                'visible_en_carta' => true,
                'created_at'       => $ahora,
                'updated_at'       => $ahora,
            ];
        }

        // Bulk insert en chunks de 100 (evita límite de parámetros PDO)
        foreach (array_chunk($productos, 100) as $chunk) {
            DB::table('productos')->insert($chunk);
        }

        // Recuperar IDs insertados
        $ids = DB::table('productos')
            ->where('empresa_id', $empresa->id)
            ->where('codigo_interno', 'like', self::PREFIX . '%')
            ->pluck('id');

        // Bulk insert inventarios con stock aleatorio
        $inventarios = $ids->map(fn ($id) => [
            'empresa_id'        => $empresa->id,
            'producto_id'       => $id,
            'variante_id'       => null,
            'stock_real'        => fake()->numberBetween(5, 200),
            'stock_reserva'     => 0,
            'stock_minimo'      => 5,
            'estado_inventario' => 'con_stock',
            'created_at'        => $ahora,
            'updated_at'        => $ahora,
        ])->toArray();

        foreach (array_chunk($inventarios, 100) as $chunk) {
            DB::table('inventarios')->insert($chunk);
        }

        $this->command->info("✓ " . self::TOTAL . " productos de prueba creados para \"{$empresa->nombre}\".");
        $this->command->line("  Para eliminarlos: php artisan db:seed --class=ProductosPruebaSeeder --fresh");
        $this->command->line("  O ejecuta:        php artisan tinker --execute=\"DB::table('productos')->where('codigo_interno','like','PT-TEST-%')->delete();\"");
    }
}

<?php

namespace Database\Factories;

use App\Enums\EstadoGeneral;
use App\Models\Empresa;
use App\Models\UnidadesMedida;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductoFactory extends Factory
{
    protected static array $adjectives = [
        'Premium', 'Especial', 'Natural', 'Clásico', 'Artesanal', 'Orgánico',
        'Fresco', 'Selecto', 'Integral', 'Ligero', 'Suave', 'Extra',
        'Dorado', 'Supremo', 'Original', 'Auténtico', 'Tradicional', 'Deluxe',
    ];

    protected static array $nouns = [
        'Leche', 'Pan', 'Arroz', 'Azúcar', 'Aceite', 'Sal', 'Harina',
        'Café', 'Té', 'Yogurt', 'Mantequilla', 'Queso', 'Huevos', 'Jugo',
        'Galletas', 'Fideos', 'Atún', 'Pollo', 'Carne', 'Pescado',
        'Tomate', 'Cebolla', 'Papa', 'Zanahoria', 'Lechuga', 'Manzana',
        'Naranja', 'Plátano', 'Pera', 'Uva', 'Fresa', 'Mango', 'Piña',
        'Jabón', 'Shampoo', 'Detergente', 'Desinfectante', 'Papel',
        'Gaseosa', 'Agua', 'Cerveza', 'Refresco', 'Helado', 'Chocolate',
        'Cereal', 'Avena', 'Granola', 'Snack', 'Caramelo', 'Mermelada',
    ];

    protected static array $presentations = [
        '250g', '500g', '1kg', '2kg', '200ml', '500ml', '1L', '6 unid.',
        'x12', 'bolsa', 'lata', 'frasco', 'caja', 'paquete', 'docena',
    ];

    public function definition(): array
    {
        $adj    = fake()->randomElement(static::$adjectives);
        $noun   = fake()->randomElement(static::$nouns);
        $pres   = fake()->randomElement(static::$presentations);
        $nombre = "{$noun} {$adj} {$pres}";

        $costo  = round(fake()->randomFloat(2, 0.50, 50.00), 2);
        $precio = round($costo * fake()->randomFloat(2, 1.10, 2.50), 2);

        return [
            'nombre'           => $nombre,
            'precio_costo'     => $costo,
            'precio_venta'     => $precio,
            'estado'           => EstadoGeneral::Activo->value,
            'control_de_stock' => true,
            'venta_sin_stock'  => false,
            'es_cortesia'      => false,
            'visible_en_carta' => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ];
    }
}

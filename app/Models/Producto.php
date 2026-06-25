<?php

namespace App\Models;

use App\Enums\EstadoGeneral;
use App\Enums\ProductoEtiqueta;
use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Producto extends Model
{
    use BelongsToEmpresa;

    protected $fillable = [
        'empresa_id',
        'categoria_id',
        'marca_id',
        'produccion_id',
        'unidad_medida_id',
        'nombre',
        'logo',
        'codigo_interno',
        'codigo_barras',
        'descripcion',
        'slug',
        'precio_costo',
        'precio_venta',
        'es_cortesia',
        'visible_en_carta',
        'control_de_stock',
        'venta_sin_stock',
        'etiqueta',
        'orden',
        'estado',
        'porcentaje_descuento',
        'precio_con_descuento',
        'es_oferta',
        'stock_minimo',
    ];

    protected $casts = [
        'es_cortesia' => 'boolean',
        'visible_en_carta' => 'boolean',
        'tiene_receta' => 'boolean',
        'control_de_stock' => 'boolean',
        'venta_sin_stock' => 'boolean',
        'precio_costo' => 'float',
        'precio_venta' => 'float',
        'estado' => EstadoGeneral::class, // Cast automático a Enum
        'etiqueta' => ProductoEtiqueta::class, // Cast automático a Enum
    ];

    // --- Relaciones ---

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    public function produccion(): BelongsTo
    {
        return $this->belongsTo(Produccion::class);
    }

    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadesMedida::class);
    }

    public function atributos()
    {
        return $this->hasMany(ProductoAtributo::class);
    }

    public function variantes()
    {
        return $this->hasMany(Variante::class);
    }

    public function variantesActivas()
    {
        return $this->hasMany(Variante::class)->where('estado', 'activo');
    }

    public function inventarios()
    {
        return $this->hasOne(Inventario::class)->whereNull('variante_id');
    }

    public function inventario()
    {
        return $this->hasOne(Inventario::class, 'producto_id')
                    ->whereNull('variante_id');
    }

    public function calcularStockTotal()
    {
        if ($this->tiene_variantes) {
            // Suma el stock de todas las variantes activas
            return $this->variantes()->where('estado', 'activo')
                ->join('inventarios', 'variantes.id', '=', 'inventarios.variantes_id')
                ->sum('inventarios.stock_real');
        }

        // Retorna el stock del producto simple
        return $this->inventario()->sum('stock_real');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}

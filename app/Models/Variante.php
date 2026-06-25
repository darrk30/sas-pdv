<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToEmpresa;

class Variante extends Model
{
    use BelongsToEmpresa;

    protected $fillable = [
        'empresa_id',
        'producto_id',
        'codigo',
        'codigo_barras',
        'estado',
        'precio_final',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function valores()
    {
        return $this->belongsToMany(ProductoAtributoValor::class, 'variante_valores', 'variante_id', 'producto_atributo_valors_id');
    }

    public function inventario()
    {
        return $this->hasOne(Inventario::class);
    }

    /**
     * Intercepta cualquier llamada $variante->delete() y la redirige a
     * desactivar en lugar de borrar, preservando el stock_real del inventario.
     * Los DELETE a nivel DB (cascade de producto) siguen funcionando normalmente.
     */
    public function delete(): bool
    {
        $this->update(['estado' => 'inactivo']);

        Inventario::where('variante_id', $this->id)
            ->update(['estado_almacen' => 'inactivo']);

        return true;
    }
}

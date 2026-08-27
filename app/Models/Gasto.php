<?php

namespace App\Models;

use App\Enums\CategoriaGasto;
use App\Enums\EstadoGasto;
use App\Traits\BelongsToEmpresa;
use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gasto extends Model
{
    use BelongsToEmpresa, BelongsToUser;

    protected $table = 'gastos';

    protected $fillable = [
        'empresa_id',
        'user_id',
        'user_empleado_id',
        'fecha',
        'monto',
        'categoria',
        'descripcion',
        'serie',
        'correlativo',
        'archivo_adjunto',
        'estado',
    ];

    protected $casts = [
        'categoria'  => CategoriaGasto::class,
        'estado'     => EstadoGasto::class,
        'fecha'      => 'date',
        'monto'      => 'decimal:2',
    ];

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_empleado_id');
    }

    public function getCodigoAttribute(): string
    {
        return $this->serie . '-' . str_pad((string) $this->correlativo, 6, '0', STR_PAD_LEFT);
    }

    public static function siguienteCorrelativo(int $empresaId, string $serie = 'G'): int
    {
        $max = static::where('empresa_id', $empresaId)
            ->where('serie', $serie)
            ->max('correlativo');

        return ($max ?? 0) + 1;
    }

    public function estaAnulado(): bool
    {
        return $this->estado === EstadoGasto::Anulado;
    }
}

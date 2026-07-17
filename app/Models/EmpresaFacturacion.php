<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpresaFacturacion extends Model
{
    protected $table = 'empresa_facturacion';

    protected $fillable = [
        'empresa_id',
        'sol_user',
        'sol_pass',
        'cert_path',
        'cert_password',
        'facturador_url',
        'facturador_api_token',
        'produccion',
    ];

    protected $casts = [
        'sol_pass'      => 'encrypted',
        'cert_password' => 'encrypted',
        'produccion'    => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}

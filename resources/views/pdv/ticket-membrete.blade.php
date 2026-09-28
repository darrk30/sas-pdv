<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 3mm 3mm; }

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: Helvetica, Arial, sans-serif;
    font-size: 8pt;
    color: #000;
    line-height: 1.3;
}

.membrete {
    padding: 3mm 3.5mm;
}

/* ── Destinatario ── */
.cliente-nombre {
    font-size: 15pt;
    font-weight: bold;
    text-transform: uppercase;
    line-height: 1.2;
    margin-bottom: 1.5mm;
    word-break: break-word;
}
.cliente-doc {
    font-size: 10pt;
    color: #222;
    margin-bottom: 2mm;
}

/* ── Divisor ── */
.divider {
    border-top: 0.5pt solid #bbb;
    margin: 2mm 0;
}

/* ── Campos ── */
.campo-label {
    font-size: 7.5pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #555;
}
.campo-valor {
    font-size: 13pt;
    font-weight: bold;
    line-height: 1.4;
    margin-bottom: 2.5mm;
    word-break: break-word;
}

/* ── Tracking ── */
.tracking-box {
    margin-top: 3mm;
    border: 1.5pt solid #000;
    padding: 2mm 2mm;
    text-align: center;
}
.tracking-label {
    font-size: 6.5pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: #555;
    margin-bottom: .5mm;
}
.tracking-code {
    font-size: 16pt;
    font-weight: bold;
    font-family: Courier, monospace;
    letter-spacing: .05em;
}
</style>
</head>
<body>
@php
    $orden  = $venta->orden;

    $clienteNombre  = $orden?->cliente_nombre ?: $venta->cliente_nombre ?: 'Cliente general';
    $clienteDoc     = $orden?->cliente_num_doc ?: $venta->cliente_num_doc;
    $clienteTipoDoc = strtoupper($orden?->cliente_tipo_doc ?: $venta->cliente_tipo_doc ?: 'Doc');

    // Geo: nuevas columnas primero, fallback a notas_internas
    $dept = $orden?->orden_departamento;
    $prov = $orden?->orden_provincia;
    $dist = $orden?->orden_distrito;
    if ($dept || $prov || $dist) {
        $geoTexto = implode(' / ', array_filter([$dept, $prov, $dist]));
    } elseif ($orden?->notas_internas) {
        $geoTexto = $orden->notas_internas;
    } else {
        $geoTexto = null;
    }

    $dirAgencia   = $orden?->direccion_agencia;
    $trackingCode = $orden?->tracking_code;
    $metodoTipo   = $orden?->metodoEnvio?->tipo;
    $dirLabel = $metodoTipo === 'provincial' ? 'Agencia' : 'Dirección de entrega';
@endphp

<div class="membrete">

    {{-- Destinatario --}}
    <div class="cliente-nombre">{{ strtoupper($clienteNombre) }}</div>
    @if ($clienteDoc)
    <div class="cliente-doc">{{ $clienteTipoDoc }}: {{ $clienteDoc }}</div>
    @endif

    <div class="divider"></div>

    @if ($geoTexto)
    <div class="campo-label">Dep / Prov / Dist</div>
    <div class="campo-valor">{{ $geoTexto }}</div>
    @endif

    @if ($dirAgencia)
    <div class="campo-label">{{ $dirLabel }}</div>
    <div class="campo-valor">{{ $dirAgencia }}</div>
    @endif

    @if ($trackingCode)
    <div class="tracking-box">
        <div class="tracking-label">Código de rastreo</div>
        <div class="tracking-code">{{ $trackingCode }}</div>
    </div>
    @endif

</div>
</body>
</html>

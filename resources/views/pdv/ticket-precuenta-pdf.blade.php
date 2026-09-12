<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: Courier, monospace; font-size:11px; width:226px; color:#000; }
.center { text-align:center; }
.bold   { font-weight:bold; }
.line   { border-top:1px dashed #000; margin:4px 0; }
.titulo { font-size:13px; font-weight:bold; text-align:center; margin-bottom:2px; }
.empresa { font-size:12px; font-weight:bold; text-align:center; margin-bottom:1px; }
.meta   { font-size:10px; margin-bottom:2px; }
table   { width:100%; border-collapse:collapse; }
td      { padding:1px 0; font-size:11px; vertical-align:top; }
td.cant { width:28px; }
td.prec { width:55px; text-align:right; }
tfoot td { border-top:1px solid #000; padding-top:2px; }
.total-row td { font-weight:bold; font-size:12px; }
</style>
</head>
<body>

<div class="empresa">{{ strtoupper($empresa->nombre ?? 'ESTABLECIMIENTO') }}</div>

<div class="titulo">PRE-CUENTA</div>

<div class="meta center">
    Mesa: <strong>{{ $orden->mesa?->nombre ?? '—' }}</strong>
    @if($orden->mesa?->piso)
        &nbsp;·&nbsp;{{ $orden->mesa->piso->nombre }}
    @endif
</div>

<div class="meta center">
    Hora: {{ now()->format('H:i') }}
    &nbsp;|&nbsp;
    Atendido por: {{ $cajeroNombre }}
</div>

<div class="meta center">
    Orden: {{ $orden->codigo }}
</div>

<div class="line"></div>

<table>
<thead>
<tr>
    <td class="cant bold">Cant</td>
    <td class="bold">Descripción</td>
    <td class="prec bold">Total</td>
</tr>
</thead>
<tbody>
@foreach($orden->detalles as $det)
<tr>
    <td class="cant">{{ (int) $det->cantidad }}x</td>
    <td>{{ $det->descripcion }}</td>
    <td class="prec">S/ {{ number_format($det->total, 2) }}</td>
</tr>
@endforeach
</tbody>
<tfoot>
<tr class="total-row">
    <td colspan="2" style="text-align:right;padding-right:4px;">TOTAL</td>
    <td class="prec">S/ {{ number_format($orden->total, 2) }}</td>
</tr>
</tfoot>
</table>

<div class="line"></div>
<div class="center" style="font-size:10px;">{{ now()->format('d/m/Y H:i:s') }}</div>
<div class="center" style="font-size:9px;margin-top:2px;">** DOCUMENTO NO VÁLIDO COMO COMPROBANTE **</div>

</body>
</html>

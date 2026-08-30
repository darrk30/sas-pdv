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
.area-badge {
    background:#000; color:#fff;
    padding:2px 6px; font-size:12px; font-weight:bold;
    text-align:center; margin-bottom:4px;
}
.titulo { font-size:13px; font-weight:bold; text-align:center; margin-bottom:2px; }
.meta   { font-size:10px; margin-bottom:2px; }
table   { width:100%; border-collapse:collapse; }
td      { padding:1px 0; font-size:11px; vertical-align:top; }
td.cant { width:30px; font-weight:bold; }
td.nota { padding-left:10px; font-size:10px; color:#444; font-style:italic; }
.cancelado { color:#777; text-decoration:line-through; }
</style>
</head>
<body>

<div class="area-badge">{{ strtoupper($areaNombre) }}</div>

<div class="titulo">
    {{ $esParcial ? 'ORDEN ACTUALIZADA' : 'NUEVA ORDEN' }}
</div>

<div class="meta center">
    Hora: {{ now()->format('H:i') }}
    &nbsp;|&nbsp;
    Usuario: {{ $cajeroNombre }}
</div>

<div class="line"></div>

@if(! empty($itemsParaImprimir['nuevos']))
<div class="bold" style="margin-bottom:2px;">AGREGAR:</div>
<table>
@foreach($itemsParaImprimir['nuevos'] as $item)
<tr>
    <td class="cant">{{ $item['cant'] }}x</td>
    <td>{{ $item['nombre'] }}</td>
</tr>
@if(! empty($item['nota']))
<tr>
    <td></td>
    <td class="nota">↳ {{ $item['nota'] }}</td>
</tr>
@endif
@endforeach
</table>
@endif

@if(! empty($itemsParaImprimir['cancelados']))
<div class="line"></div>
<div class="bold" style="margin-bottom:2px;">QUITAR:</div>
<table>
@foreach($itemsParaImprimir['cancelados'] as $item)
<tr class="cancelado">
    <td class="cant">{{ $item['cant'] }}x</td>
    <td>{{ $item['nombre'] }}</td>
</tr>
@endforeach
</table>
@endif

<div class="line"></div>
<div class="center" style="font-size:10px;">{{ now()->format('d/m/Y H:i:s') }}</div>

</body>
</html>

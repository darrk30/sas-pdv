@props(['etiqueta' => null])

@if ($etiqueta)
<span class="prod-etiqueta prod-etiqueta--{{ $etiqueta->value }}">
    {{ $etiqueta->getLabel() }}
</span>
@endif

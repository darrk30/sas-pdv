<?php

if (! function_exists('fmt_precio')) {
    /**
     * Formatea un precio con mínimo 2 decimales y máximo 4,
     * eliminando ceros innecesarios después del 2° decimal.
     * Ej: 2.3500 → "2.35" | 2.50 → "2.50" | 2.3512 → "2.3512"
     */
    function fmt_precio(float|int|string|null $valor, string $prefijo = ''): string
    {
        $n = (float) ($valor ?? 0);
        $s = rtrim(number_format($n, 4, '.', ''), '0');
        $dot = strpos($s, '.');
        if ($dot === false) {
            $s .= '.00';
        } elseif (strlen($s) - $dot - 1 < 2) {
            $s = str_pad($s, strlen($s) + (2 - (strlen($s) - $dot - 1)), '0');
        }
        return $prefijo . $s;
    }
}

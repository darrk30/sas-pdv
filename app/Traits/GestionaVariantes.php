<?php

namespace App\Traits;

trait GestionaVariantes
{
    private function debeGenerarVariantes(array $atributos): bool
    {
        $totalValores = 0;
        $totalAtributos = 0;
        foreach ($atributos as $item) {
            if (!empty($item['valores_seleccionados'])) {
                $totalAtributos++;
                $totalValores += count($item['valores_seleccionados']);
            }
        }
        return $totalValores > $totalAtributos;
    }
}
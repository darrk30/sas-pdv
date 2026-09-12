<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TipoOrigenOrden: string implements HasLabel
{
    case Web         = 'web';
    case Restaurante = 'restaurante';

    public function getLabel(): string
    {
        return match ($this) {
            self::Web         => 'Pedido Web',
            self::Restaurante => 'Mesa / Restaurante',
        };
    }
}

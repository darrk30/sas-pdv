<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TipoOrigenOrden: string implements HasLabel
{
    case Web         = 'web';
    case Restaurante = 'restaurante';
    case Llevar      = 'llevar';
    case Delivery    = 'delivery';

    public function getLabel(): string
    {
        return match ($this) {
            self::Web         => 'Pedido Web',
            self::Restaurante => 'Mesa / Restaurante',
            self::Llevar      => 'Para llevar',
            self::Delivery    => 'Delivery',
        };
    }
}

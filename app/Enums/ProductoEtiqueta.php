<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProductoEtiqueta: string implements HasLabel, HasColor
{
    case NUEVO = 'nuevo';
    case AGOTADO = 'agotado';
    case COMBO = 'combo';
    case PROMO = 'promo';
    case OFERTA = 'oferta';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::NUEVO => 'Nuevo',
            self::AGOTADO => 'Agotado',
            self::COMBO => 'Combo',
            self::PROMO => 'Promoción',
            self::OFERTA => 'Oferta',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::NUEVO => 'info',    // Azul
            self::AGOTADO => 'danger', // Rojo
            self::COMBO => 'primary',  // Morado/Indigo
            self::PROMO => 'warning',  // Naranja
            self::OFERTA => 'success', // Verde
        };
    }
}
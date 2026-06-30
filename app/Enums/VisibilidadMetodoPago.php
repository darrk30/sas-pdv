<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum VisibilidadMetodoPago: string implements HasLabel, HasColor
{
    case Web   = 'web';
    case PDV   = 'pdv';
    case Ambos = 'ambos';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Web   => 'Solo Web',
            self::PDV   => 'Solo PDV',
            self::Ambos => 'Web y PDV',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Web   => 'info',
            self::PDV   => 'warning',
            self::Ambos => 'success',
        };
    }
}

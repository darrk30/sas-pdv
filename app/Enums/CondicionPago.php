<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CondicionPago: string implements HasLabel, HasColor
{
    case Contado = 'contado';
    case Credito = 'credito';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Contado => 'Contado',
            self::Credito => 'Crédito',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Contado => 'success',
            self::Credito => 'warning',
        };
    }
}

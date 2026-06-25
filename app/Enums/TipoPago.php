<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TipoPago: string implements HasLabel, HasColor, HasIcon
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

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Contado => 'heroicon-o-banknotes',
            self::Credito => 'heroicon-o-clock',
        };
    }
}

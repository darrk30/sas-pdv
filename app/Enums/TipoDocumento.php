<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TipoDocumento: string implements HasLabel
{
    case DNI = 'dni';
    case RUC = 'ruc';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DNI => 'DNI',
            self::RUC => 'RUC',
        };
    }

    public function maxLength(): int
    {
        return match ($this) {
            self::DNI => 8,
            self::RUC => 11,
        };
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TipoNota: string implements HasLabel, HasColor
{
    case Credito = 'credito';
    case Debito  = 'debito';

    public function getLabel(): string
    {
        return match ($this) {
            self::Credito => 'Nota Crédito',
            self::Debito  => 'Nota Débito',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Credito => 'success',
            self::Debito  => 'warning',
        };
    }

    public function tipoDocSunat(): string
    {
        return match ($this) {
            self::Credito => '07',
            self::Debito  => '08',
        };
    }
}

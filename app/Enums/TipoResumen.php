<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TipoResumen: string implements HasLabel, HasColor
{
    case Diario      = 'diario';
    case Bajas       = 'bajas';
    case NotasDiario = 'notas_diario';
    case NotasBajas  = 'notas_bajas';

    public function getLabel(): string
    {
        return match ($this) {
            self::Diario      => 'RC Diario',
            self::Bajas       => 'RA Baja',
            self::NotasDiario => 'RC Notas',
            self::NotasBajas  => 'RA Notas',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Diario      => 'info',
            self::Bajas       => 'warning',
            self::NotasDiario => 'success',
            self::NotasBajas  => 'danger',
        };
    }

    public function esParaNotas(): bool
    {
        return in_array($this, [self::NotasDiario, self::NotasBajas]);
    }

    public function esRA(): bool
    {
        return in_array($this, [self::Bajas, self::NotasBajas]);
    }
}

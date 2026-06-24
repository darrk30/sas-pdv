<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CategoriaEgreso: string implements HasLabel, HasColor
{
    case Remuneracion = 'remuneracion';
    case Compra       = 'compra';
    case Servicio     = 'servicio';
    case OtroGasto    = 'otro_gasto';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Remuneracion => 'Remuneración',
            self::Compra       => 'Compra',
            self::Servicio     => 'Servicio',
            self::OtroGasto    => 'Otro Gasto',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Remuneracion => 'info',
            self::Compra       => 'warning',
            self::Servicio     => 'primary',
            self::OtroGasto    => 'gray',
        };
    }
}

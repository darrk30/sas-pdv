<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum CategoriaGasto: string implements HasLabel, HasColor, HasIcon
{
    case Alquiler       = 'alquiler';
    case Servicio       = 'servicio';
    case Remuneracion   = 'remuneracion';
    case Suministros    = 'suministros';
    case Transporte     = 'transporte';
    case GastoPersonal  = 'gasto_personal';
    case Otro           = 'otro';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Alquiler      => 'Alquiler',
            self::Servicio      => 'Servicio',
            self::Remuneracion  => 'Remuneración',
            self::Suministros   => 'Suministros',
            self::Transporte    => 'Transporte',
            self::GastoPersonal => 'Gasto Personal',
            self::Otro          => 'Otro',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Alquiler      => 'indigo',
            self::Servicio      => 'primary',
            self::Remuneracion  => 'info',
            self::Suministros   => 'warning',
            self::Transporte    => 'success',
            self::GastoPersonal => 'gray',
            self::Otro          => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Alquiler      => 'heroicon-o-home',
            self::Servicio      => 'heroicon-o-wrench-screwdriver',
            self::Remuneracion  => 'heroicon-o-user-group',
            self::Suministros   => 'heroicon-o-archive-box',
            self::Transporte    => 'heroicon-o-truck',
            self::GastoPersonal => 'heroicon-o-user',
            self::Otro          => 'heroicon-o-ellipsis-horizontal-circle',
        };
    }
}

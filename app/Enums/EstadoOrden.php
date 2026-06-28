<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum EstadoOrden: string implements HasLabel, HasColor, HasIcon
{
    case Borrador   = 'borrador';
    case Pendiente  = 'pendiente';
    case Aprobada   = 'aprobada';
    case EnProceso  = 'en_proceso';
    case Completada = 'completada';
    case Cancelada  = 'cancelada';

    public function getLabel(): string
    {
        return match ($this) {
            self::Borrador   => 'Borrador',
            self::Pendiente  => 'Pendiente',
            self::Aprobada   => 'Aprobada',
            self::EnProceso  => 'En proceso',
            self::Completada => 'Completada',
            self::Cancelada  => 'Cancelada',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Borrador   => 'gray',
            self::Pendiente  => 'warning',
            self::Aprobada   => 'info',
            self::EnProceso  => 'primary',
            self::Completada => 'success',
            self::Cancelada  => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Borrador   => 'heroicon-o-pencil',
            self::Pendiente  => 'heroicon-o-clock',
            self::Aprobada   => 'heroicon-o-check',
            self::EnProceso  => 'heroicon-o-cog-6-tooth',
            self::Completada => 'heroicon-o-check-circle',
            self::Cancelada  => 'heroicon-o-x-circle',
        };
    }

    /** Estados que pueden seguir a este estado. */
    public function transicionesPosibles(): array
    {
        return match ($this) {
            self::Borrador   => [self::Pendiente, self::Cancelada],
            self::Pendiente  => [self::Aprobada, self::Cancelada],
            self::Aprobada   => [self::EnProceso, self::Cancelada],
            self::EnProceso  => [self::Completada, self::Cancelada],
            self::Completada => [],
            self::Cancelada  => [],
        };
    }

    public function esFinal(): bool
    {
        return in_array($this, [self::Completada, self::Cancelada]);
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EstadoGeneral: string implements HasLabel
{
    case Activo = 'activo';
    case Inactivo = 'inactivo';
    case Archivado = 'archivado';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::Inactivo => 'Inactivo',
            self::Archivado => 'Archivado',
        };
    }

    // Método estático para obtener colores rápidamente
    public function getColor(): string
    {
        return match ($this) {
            self::Activo => 'success',
            self::Inactivo => 'danger',
            self::Archivado => 'gray',
        };
    }
}

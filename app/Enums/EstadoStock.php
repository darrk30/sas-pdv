<?php

namespace App\Enums;

enum EstadoStock: string
{
    case Disponible = 'disponible';
    case PorAgotarse = 'por_agotarse';
    case Agotado = 'agotado';
}
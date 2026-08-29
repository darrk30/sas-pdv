<?php

namespace App\Filament\Resources\TareasProgramadas;

use App\Console\Commands\ResetUsosPromocionesCommand;
use App\Console\Commands\VencerSuscripcionesCommand;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;

class TareaProgramadaForm
{
    // Comandos disponibles para registrar como tareas
    public static array $comandosDisponibles = [
        'suscripciones:vencer'    => 'Vencer suscripciones expiradas y marcar empresas inactivas',
        'promociones:reset-usos'  => 'Resetear usos de promociones a 0',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Información de la Tarea')
                ->columns(2)
                ->schema([
                    TextInput::make('nombre')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(100)
                        ->columnSpanFull(),

                    Select::make('comando')
                        ->label('Comando Artisan')
                        ->options(self::$comandosDisponibles)
                        ->required()
                        ->native(false)
                        ->helperText('Selecciona el comando que se ejecutará')
                        ->columnSpanFull()
                        ->live()
                        ->afterStateUpdated(function ($state, $set) {
                            if ($state && isset(self::$comandosDisponibles[$state])) {
                                $set('descripcion', self::$comandosDisponibles[$state]);
                            }
                        }),

                    Textarea::make('descripcion')
                        ->label('Descripción')
                        ->rows(2)
                        ->maxLength(255)
                        ->columnSpanFull(),

                    TextInput::make('hora')
                        ->label('Hora de ejecución')
                        ->required()
                        ->placeholder('HH:MM')
                        ->helperText('Formato 24h — ej: 00:05, 08:30, 23:00')
                        ->regex('/^([01]\d|2[0-3]):[0-5]\d$/')
                        ->validationMessages(['regex' => 'Formato inválido. Usa HH:MM en 24h.'])
                        ->default('00:00'),

                    Toggle::make('activo')
                        ->label('Activa')
                        ->helperText('Solo las tareas activas se ejecutan automáticamente')
                        ->default(true)
                        ->onColor('success'),
                ]),
        ]);
    }
}

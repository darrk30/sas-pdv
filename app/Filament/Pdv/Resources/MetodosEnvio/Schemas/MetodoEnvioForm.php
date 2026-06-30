<?php

namespace App\Filament\Pdv\Resources\MetodosEnvio\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MetodoEnvioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del método de envío')
                    ->columns(2)
                    ->schema([

                        TextInput::make('nombre')
                            ->label('Nombre')
                            ->placeholder('Ej: AGENCIA SHALOM')
                            ->required()
                            ->maxLength(255),

                        RichEditor::make('descripcion')
                            ->label('Descripción')
                            ->placeholder('Ej: 3 a 5 días hábiles')
                            ->nullable()
                            ->toolbarButtons(['bold', 'italic', 'link', 'bulletList'])
                            ->columnSpanFull(),

                        TextInput::make('costo')
                            ->label('Costo')
                            ->numeric()
                            ->prefix('S/')
                            ->minValue(0)
                            ->default(0)
                            ->required(),

                        Select::make('estado')
                            ->label('Estado')
                            ->options(['activo' => 'Activo', 'inactivo' => 'Inactivo'])
                            ->native(false)
                            ->default('activo')
                            ->required(),

                        Toggle::make('con_direccion')
                            ->label('Requiere dirección de entrega')
                            ->helperText('Al activar, el checkout mostrará un campo para ingresar la dirección de envío.')
                            ->default(true)
                            ->columnSpanFull(),

                    ]),
            ]);
    }
}

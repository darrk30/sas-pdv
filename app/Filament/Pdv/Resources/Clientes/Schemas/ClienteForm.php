<?php

namespace App\Filament\Pdv\Resources\Clientes\Schemas;

use App\Enums\TipoDocumento;
use App\Models\Cliente;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClienteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del cliente')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([

                            Select::make('tipo_documento')
                                ->label('Tipo de documento')
                                ->options(TipoDocumento::class)
                                ->native(false)
                                ->required()
                                ->live(),

                            TextInput::make('numero_documento')
                                ->label('Número de documento')
                                ->required()
                                ->maxLength(fn($get) => TipoDocumento::tryFrom($get('tipo_documento'))?->maxLength() ?? 20)
                                ->numeric()
                                ->unique(
                                    table: Cliente::class,
                                    column: 'numero_documento',
                                    ignoreRecord: true,
                                    modifyRuleUsing: fn($rule) => $rule->where('empresa_id', Filament::getTenant()->id)
                                )
                                ->validationMessages([
                                    'unique' => 'Este número de documento ya está registrado en la empresa.',
                                ]),

                            TextInput::make('nombre')
                                ->label('Nombre / Razón social')
                                ->required()
                                ->maxLength(255),

                            TextInput::make('apellidos')
                                ->label('Apellidos')
                                ->nullable()
                                ->maxLength(255),

                            TextInput::make('telefono')
                                ->label('Teléfono')
                                ->tel()
                                ->nullable()
                                ->maxLength(20),

                            TextInput::make('correo')
                                ->label('Correo electrónico')
                                ->email()
                                ->nullable()
                                ->maxLength(255),

                            TextInput::make('direccion')
                                ->label('Dirección')
                                ->nullable()
                                ->maxLength(255)
                                ->columnSpanFull(),

                        ]),
                    ])->columnSpanFull(),
            ]);
    }
}

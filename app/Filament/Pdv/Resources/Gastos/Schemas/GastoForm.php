<?php

namespace App\Filament\Pdv\Resources\Gastos\Schemas;

use App\Enums\CategoriaGasto;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GastoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos del Gasto')
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])->schema([

                        DatePicker::make('fecha')
                            ->label('Fecha')
                            ->required()
                            ->default(now())
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        TextInput::make('monto')
                            ->label('Monto (S/)')
                            ->numeric()
                            ->minValue(0.01)
                            ->required()
                            ->placeholder('0.00'),

                        Select::make('categoria')
                            ->label('Categoría')
                            ->options(CategoriaGasto::class)
                            ->native(false)
                            ->required()
                            ->live(),

                        // Solo visible si categoría = remuneracion
                        Select::make('user_empleado_id')
                            ->label('Empleado (receptor)')
                            ->options(fn () => User::whereHas(
                                'empresas',
                                fn ($q) => $q->where('empresas.id', Filament::getTenant()->id)
                            )->pluck('name', 'id'))
                            ->native(false)
                            ->searchable()
                            ->required()
                            ->visible(fn ($get) => self::categoria($get('categoria')) === CategoriaGasto::Remuneracion->value)
                            ->placeholder('Buscar empleado...'),

                        Textarea::make('descripcion')
                            ->label('Descripción')
                            ->required()
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder('Detalle del gasto...'),

                        FileUpload::make('archivo_adjunto')
                            ->label('Comprobante adjunto (foto/PDF)')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                            ->directory('gastos-adjuntos')
                            ->maxSize(5120)
                            ->columnSpanFull()
                            ->helperText('Máx. 5 MB. Formatos: JPG, PNG, WEBP, PDF.'),

                    ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    private static function categoria(mixed $v): string
    {
        return $v instanceof CategoriaGasto ? $v->value : (string) $v;
    }
}

<?php

namespace App\Filament\Resources\TareasProgramadas;

use App\Models\TareaProgramada;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class TareaProgramadaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('comando')
                    ->label('Comando')
                    ->fontFamily('mono')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('hora')
                    ->label('Hora')
                    ->badge()
                    ->color('info'),

                ToggleColumn::make('activo')
                    ->label('Activa')
                    ->onColor('success'),

                TextColumn::make('ultima_ejecucion')
                    ->label('Última ejecución')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Nunca')
                    ->sortable(),

                TextColumn::make('ultimo_resultado')
                    ->label('Último resultado')
                    ->placeholder('—')
                    ->limit(80)
                    ->tooltip(fn ($record) => $record->ultimo_resultado)
                    ->wrap(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('ejecutar')
                        ->label('Ejecutar ahora')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Ejecutar tarea')
                        ->modalDescription(fn (TareaProgramada $record) => "¿Ejecutar «{$record->nombre}» ahora?")
                        ->modalSubmitActionLabel('Ejecutar')
                        ->action(function (TareaProgramada $record) {
                            $record->ejecutar();

                            Notification::make()
                                ->title('Tarea ejecutada')
                                ->body($record->ultimo_resultado)
                                ->success()
                                ->send();
                        }),

                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->defaultSort('hora');
    }
}

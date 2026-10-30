<?php

namespace App\Filament\Pdv\Resources\TareasProgramadasPdv;

use App\Filament\Pdv\Resources\TareasProgramadasPdv\Pages\CreateTareaProgramadaPdv;
use App\Filament\Pdv\Resources\TareasProgramadasPdv\Pages\EditTareaProgramadaPdv;
use App\Filament\Pdv\Resources\TareasProgramadasPdv\Pages\ListTareasProgramadasPdv;
use App\Models\TareaProgramadaPdv;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

class TareaProgramadaPdvResource extends Resource
{
    protected static ?string $model                         = TareaProgramadaPdv::class;
    protected static ?string $slug                          = 'tareas-programadas';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel               = 'Tareas Programadas';
    protected static ?string $modelLabel                    = 'Tarea Programada';
    protected static ?string $pluralModelLabel              = 'Tareas Programadas';
    protected static string|UnitEnum|null $navigationGroup  = 'Facturación Electrónica';
    protected static ?int    $navigationSort                = 10;

    public static function canAccess(): bool
    {
        $empresa = Filament::getTenant();
        if (! $empresa) {
            return false;
        }

        return $empresa->tieneFacturacionElectronica();
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->where('empresa_id', Filament::getTenant()->id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Información de la Tarea')
                ->description('Configura cuándo y qué proceso se ejecutará automáticamente.')
                ->columns(2)
                ->schema([
                    TextInput::make('nombre')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(100)
                        ->placeholder('Ej: Envío diario nocturno')
                        ->columnSpanFull(),

                    Select::make('tipo')
                        ->label('Proceso')
                        ->options([
                            'envio_boletas_rc' => 'Envío de Boletas RC (Resumen)',
                            'reenvio_facturas' => 'Reenvío de Facturas pendientes',
                        ])
                        ->required()
                        ->default('envio_boletas_rc')
                        ->native(false)
                        ->live()
                        ->columnSpanFull()
                        ->helperText(fn (Get $get) => match ($get('tipo')) {
                            'reenvio_facturas' => 'Envía a SUNAT todas las facturas en estado "Por Enviar" emitidas en los últimos 3 días.',
                            default            => 'Genera y envía el Resumen de Comprobantes (RC) de boletas al SOL de SUNAT.',
                        }),

                    Select::make('fecha_offset')
                        ->label('Boletas a procesar')
                        ->options([
                            -1 => 'Solo hoy',
                            -7 => 'Hoy + últimos 7 días (recomendado)',
                        ])
                        ->default(-7)
                        ->required()
                        ->native(false)
                        ->visible(fn (Get $get) => $get('tipo') === 'envio_boletas_rc')
                        ->helperText('Se incluyen boletas "por enviar" desde la fecha indicada hasta HOY. "Solo hoy" envía únicamente las de la jornada actual.'),

                    TextInput::make('hora')
                        ->label('Hora de ejecución')
                        ->required()
                        ->placeholder('HH:MM')
                        ->helperText('Formato 24h — ej: 23:00, 01:30')
                        ->regex('/^([01]\d|2[0-3]):[0-5]\d$/')
                        ->validationMessages(['regex' => 'Formato inválido. Usa HH:MM en 24h.'])
                        ->default('23:00'),

                    CheckboxList::make('dias_semana')
                        ->label('Días de ejecución')
                        ->options([
                            1 => 'Lunes',
                            2 => 'Martes',
                            3 => 'Miércoles',
                            4 => 'Jueves',
                            5 => 'Viernes',
                            6 => 'Sábado',
                            0 => 'Domingo',
                        ])
                        ->helperText('Deja en blanco para ejecutar todos los días de la semana')
                        ->columns(4)
                        ->bulkToggleable()
                        ->columnSpanFull(),

                    Toggle::make('activo')
                        ->label('Tarea activa')
                        ->helperText('Solo las tareas activas se ejecutan automáticamente')
                        ->default(true)
                        ->onColor('success'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'envio_boletas_rc' => 'Boletas RC',
                        'reenvio_facturas' => 'Facturas',
                        default            => ucfirst($state),
                    })
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'envio_boletas_rc' => 'info',
                        'reenvio_facturas' => 'warning',
                        default            => 'gray',
                    }),

                TextColumn::make('hora')
                    ->label('Hora')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('dias_semana')
                    ->label('Días')
                    ->formatStateUsing(function ($state) {
                        if (empty($state)) {
                            return 'Todos los días';
                        }
                        $nombres = [0 => 'Dom', 1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb'];
                        $dias    = array_map(fn ($d) => $nombres[$d] ?? $d, (array) $state);
                        return implode(', ', $dias);
                    }),


                TextColumn::make('fecha_offset')
                    ->label('Rango boletas')
                    ->formatStateUsing(fn ($state, $record) => $record->tipo === 'reenvio_facturas'
                        ? 'Últimos 3 días'
                        : match ((int) $state) {
                            -1      => 'Solo hoy',
                            -7      => 'Hoy + 7 días',
                            default => 'Últimos ' . abs((int) $state) . ' días',
                        })
                    ->badge()
                    ->color('warning'),

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
                    ->limit(50)
                    ->wrap(),
            ])
            ->recordActions([
                Action::make('ver_resultado')
                    ->label('')
                    ->tooltip('Ver resultado detallado')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->color('gray')
                    ->modalHeading(fn (TareaProgramadaPdv $record) => 'Resultado — ' . $record->nombre)
                    ->modalContent(fn (TareaProgramadaPdv $record) => new HtmlString(
                        self::buildResultadoHtml($record)
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->visible(fn ($record) => filled($record->ultimo_resultado)),

                ActionGroup::make([
                    Action::make('ejecutar')
                        ->label('Ejecutar ahora')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Ejecutar tarea')
                        ->modalDescription(fn (TareaProgramadaPdv $record) => "¿Ejecutar «{$record->nombre}» ahora? El proceso puede tardar varios minutos.")
                        ->modalSubmitActionLabel('Ejecutar')
                        ->action(function (TareaProgramadaPdv $record) {
                            $record->update(['ultimo_resultado' => 'En proceso... (despachado a la cola)']);
                            \App\Jobs\ProcesarRcTareaJob::dispatch($record->id);

                            Notification::make()
                                ->title('Tarea despachada')
                                ->body("«{$record->nombre}» se está ejecutando en segundo plano. El resultado aparecerá en unos minutos.")
                                ->info()
                                ->send();
                        }),

                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->defaultSort('hora')
            ->emptyStateHeading('Sin tareas programadas')
            ->emptyStateDescription('Crea una tarea para automatizar el envío de boletas a SUNAT en el horario que prefieras.')
            ->emptyStateIcon('heroicon-o-clock');
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListTareasProgramadasPdv::route('/'),
            'create' => CreateTareaProgramadaPdv::route('/create'),
            'edit'   => EditTareaProgramadaPdv::route('/{record}/edit'),
        ];
    }

    private static function buildResultadoHtml(TareaProgramadaPdv $record): string
    {
        $resultado = $record->ultimo_resultado ?? '';
        $ejecucion = $record->ultima_ejecucion?->format('d/m/Y H:i') ?? '—';

        $partes = array_values(array_filter(array_map('trim', explode(' | ', $resultado))));

        $html  = '<div class="text-xs text-gray-500 dark:text-gray-400 mb-3">';
        $html .= 'Ejecutado el ' . htmlspecialchars($ejecucion);
        $html .= '</div>';
        $html .= '<div class="space-y-2">';

        if (empty($partes)) {
            $html .= '<p class="text-sm text-gray-500">Sin resultado registrado.</p>';
        }

        foreach ($partes as $parte) {
            $lower = mb_strtolower($parte);

            if (str_contains($lower, 'aceptado')) {
                $bg   = 'background-color:#f0fdf4';
                $bc   = 'border-color:#86efac';
                $tc   = 'color:#15803d';
                $icon = '✓';
            } elseif (str_contains($lower, 'rechazado') || str_contains($lower, 'error')) {
                $bg   = 'background-color:#fef2f2';
                $bc   = 'border-color:#fca5a5';
                $tc   = 'color:#b91c1c';
                $icon = '✗';
            } elseif (str_contains($lower, 'pendiente') || str_contains($lower, 'retoma') || str_contains($lower, 'procesando')) {
                $bg   = 'background-color:#fffbeb';
                $bc   = 'border-color:#fcd34d';
                $tc   = 'color:#b45309';
                $icon = '⏳';
            } elseif (str_contains($lower, 'enviado') || str_contains($lower, 'ticket') || str_contains($lower, 'rc-')) {
                $bg   = 'background-color:#eff6ff';
                $bc   = 'border-color:#93c5fd';
                $tc   = 'color:#1d4ed8';
                $icon = '→';
            } elseif (str_contains($lower, 'en proceso') || str_contains($lower, 'despachado')) {
                $bg   = 'background-color:#f0f9ff';
                $bc   = 'border-color:#7dd3fc';
                $tc   = 'color:#0369a1';
                $icon = '⋯';
            } else {
                $bg   = 'background-color:#f9fafb';
                $bc   = 'border-color:#e5e7eb';
                $tc   = 'color:#6b7280';
                $icon = '—';
            }

            $texto = htmlspecialchars($parte);
            $html .= "<div style=\"display:flex;gap:10px;align-items:flex-start;padding:10px 12px;border-radius:8px;border:1px solid;{$bg};{$bc}\">";
            $html .= "<span style=\"{$tc};font-weight:700;font-size:16px;line-height:1.2;flex-shrink:0\">{$icon}</span>";
            $html .= "<span style=\"{$tc};font-size:13px;line-height:1.5\">{$texto}</span>";
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }
}

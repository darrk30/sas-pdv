<?php

namespace App\Filament\Pdv\Resources\GastosFijos;

use App\Enums\FrecuenciaGasto;
use App\Filament\Pdv\Resources\GastosFijos\Pages\CreateGastoFijo;
use App\Filament\Pdv\Resources\GastosFijos\Pages\EditGastoFijo;
use App\Filament\Pdv\Resources\GastosFijos\Pages\ListGastosFijos;
use App\Models\GastoFijo;
use BackedEnum;
use UnitEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Forms;
use Illuminate\Database\Eloquent\Model;

class GastoFijoResource extends Resource
{
    protected static ?string $model = GastoFijo::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Gastos Fijos';

    protected static string|UnitEnum|null $navigationGroup = 'Gastos';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Gasto fijo';

    protected static ?string $pluralModelLabel = 'Gastos fijos';

    protected static ?string $recordTitleAttribute = 'nombre';

    protected static ?string $slug = 'gastos-fijos';

    public static function canAccess(): bool        { return Filament::getTenant()->tieneModulo('gastos') && (auth()->user()?->can('gastos_fijos.ver') ?? false); }
    public static function canCreate(): bool        { return auth()->user()?->can('gastos_fijos.crear') ?? false; }
    public static function canEdit(Model $r): bool  { return auth()->user()?->can('gastos_fijos.editar') ?? false; }
    public static function canDelete(Model $r): bool { return auth()->user()?->can('gastos_fijos.eliminar') ?? false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('nombre')
                ->label('Nombre del gasto')
                ->placeholder('Ej: Alquiler local, Internet, Planilla...')
                ->required()
                ->maxLength(120)
                ->columnSpanFull(),

            Forms\Components\TextInput::make('monto')
                ->label('Monto (S/)')
                ->numeric()
                ->prefix('S/')
                ->minValue(0.01)
                ->required(),

            Forms\Components\Select::make('frecuencia')
                ->label('Frecuencia')
                ->options(collect(FrecuenciaGasto::cases())->mapWithKeys(fn ($c) => [$c->value => $c->getLabel()]))
                ->required()
                ->default('mensual'),

            Forms\Components\Select::make('estado')
                ->label('Estado')
                ->options(['activo' => 'Activo', 'inactivo' => 'Inactivo'])
                ->required()
                ->default('activo'),

            Forms\Components\Textarea::make('notas')
                ->label('Notas')
                ->placeholder('Observaciones, fecha de vencimiento, proveedor...')
                ->rows(3)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Gasto')
                    ->searchable()
                    ->weight('medium'),

                TextColumn::make('monto')
                    ->label('Monto')
                    ->money('PEN')
                    ->sortable(),

                TextColumn::make('frecuencia')
                    ->label('Frecuencia')
                    ->badge()
                    ->sortable(),

                TextColumn::make('monto_mensual')
                    ->label('Equiv. mensual')
                    ->getStateUsing(fn (GastoFijo $r) => 'S/ ' . number_format(
                        $r->frecuencia->montoEnPeriodo((float) $r->monto, FrecuenciaGasto::Mensual),
                        2
                    ))
                    ->color('gray'),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'activo'   => 'success',
                        'inactivo' => 'danger',
                        default    => 'gray',
                    }),

                TextColumn::make('notas')
                    ->label('Notas')
                    ->limit(40)
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('frecuencia')
                    ->label('Frecuencia')
                    ->options(collect(FrecuenciaGasto::cases())->mapWithKeys(fn ($c) => [$c->value => $c->getLabel()])),

                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(['activo' => 'Activo', 'inactivo' => 'Inactivo']),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('nombre')
            ->emptyStateHeading('Sin gastos fijos registrados')
            ->emptyStateDescription('Agrega tus gastos fijos (alquiler, servicios, planilla…) para calcular tu meta mínima de ventas.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListGastosFijos::route('/'),
            'create' => CreateGastoFijo::route('/create'),
            'edit'   => EditGastoFijo::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('empresa_id', Filament::getTenant()->id);
    }
}

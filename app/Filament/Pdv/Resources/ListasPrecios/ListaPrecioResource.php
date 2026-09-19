<?php

namespace App\Filament\Pdv\Resources\ListasPrecios;

use App\Filament\Pdv\Resources\ListasPrecios\Pages\CreateListaPrecio;
use App\Filament\Pdv\Resources\ListasPrecios\Pages\EditListaPrecio;
use App\Filament\Pdv\Resources\ListasPrecios\Pages\ListListasPrecios;
use App\Models\ListaPrecio;
use BackedEnum;
use UnitEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ListaPrecioResource extends Resource
{
    protected static ?string $model = ListaPrecio::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Listas de Precios';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Lista de precios';

    protected static ?string $pluralModelLabel = 'Listas de precios';

    public static function canAccess(): bool
    {
        $empresa = Filament::getTenant();
        return ($empresa?->tienePlanListaPrecios() ?? true) && (auth()->user()?->can('listas_precios.ver') ?? false);
    }

    public static function canCreate(): bool            { return auth()->user()?->can('listas_precios.crear') ?? false; }
    public static function canEdit(Model $record): bool { return auth()->user()?->can('listas_precios.editar') ?? false; }
    public static function canDelete(Model $record): bool { return auth()->user()?->can('listas_precios.eliminar') ?? false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('empresa_id', Filament::getTenant()->id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nombre')
                ->label('Nombre')
                ->placeholder('Ej: Minorista, Mayorista, Distribuidor…')
                ->required()
                ->maxLength(80)
                ->columnSpanFull(),

            Toggle::make('activa')
                ->label('Lista activa')
                ->helperText('Las listas inactivas no aparecen en el formulario de productos.')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('activa')
                    ->label('Activa')
                    ->boolean(),

                TextColumn::make('preciosProducto_count')
                    ->label('Productos')
                    ->counts('preciosProducto')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('created_at')
                    ->label('Creada')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('nombre');
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListListasPrecios::route('/'),
            'create' => CreateListaPrecio::route('/create'),
            'edit'   => EditListaPrecio::route('/{record}/edit'),
        ];
    }
}

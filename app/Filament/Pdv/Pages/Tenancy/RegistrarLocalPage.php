<?php

namespace App\Filament\Pdv\Pages\Tenancy;

use App\Models\Empresa;
use App\Models\Role;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Notifications\Notification;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class RegistrarLocalPage extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Agregar nuevo local';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Datos del local')
                ->description('Información del nuevo local o sucursal. El RUC se hereda automáticamente de tu empresa.')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre del local / sucursal')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    TextInput::make('email')
                        ->label('Correo electrónico')
                        ->email()
                        ->maxLength(255),

                    TextInput::make('telefono')
                        ->label('Teléfono')
                        ->tel()
                        ->maxLength(20),
                ]),

            Section::make('Ubicación')
                ->columns(2)
                ->schema([
                    TextInput::make('direccion')
                        ->label('Dirección')
                        ->maxLength(255)
                        ->columnSpanFull(),

                    TextInput::make('departamento')
                        ->label('Departamento')
                        ->maxLength(100),

                    TextInput::make('provincia')
                        ->label('Provincia')
                        ->maxLength(100),

                    TextInput::make('distrito')
                        ->label('Distrito')
                        ->maxLength(100),
                ]),
        ]);
    }

    protected function mutateFormDataBeforeRegister(array $data): array
    {
        // Heredar RUC y país del tenant actual
        $padre = Filament::getTenant();
        if ($padre) {
            $data['ruc']          = $padre->ruc;
            $data['country_code'] = $padre->country_code ?? 'PE';
        }

        // Generar slug único desde el nombre
        $base  = Str::slug($data['name']);
        $slug  = $base;
        $count = 2;
        while (Empresa::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $count++;
        }
        $data['slug'] = $slug;

        $data['estado']               = 'activo';
        $data['carta_activa_cliente'] = 'inactivo';
        $data['carta_activa_admin']   = 'inactivo';

        return $data;
    }

    protected function handleRegistration(array $data): Model
    {
        $empresa = Empresa::create($data);

        // Vincular al usuario actual como miembro activo
        $empresa->usuarios()->attach(auth()->id(), ['estado' => 'activo']);

        // Asignar rol Administrador (creado por EmpresaObserver vía RolesEmpresaSeeder)
        $role = Role::where('name', 'Administrador')
            ->where('empresa_id', $empresa->id)
            ->first();

        if ($role) {
            $registrar = app(PermissionRegistrar::class);
            $registrar->setPermissionsTeamId($empresa->id);
            auth()->user()->assignRole($role);
            $registrar->forgetCachedPermissions();
        }

        Notification::make()
            ->success()
            ->title('Local creado correctamente')
            ->body("El local \"{$empresa->name}\" fue agregado. Puedes cambiarlo desde el selector de tienda.")
            ->send();

        return $empresa;
    }
}

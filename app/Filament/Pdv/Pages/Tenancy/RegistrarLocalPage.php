<?php

namespace App\Filament\Pdv\Pages\Tenancy;

use App\Models\Empresa;
use App\Models\Role;
use Filament\Facades\Filament;
use Filament\Support\Exceptions\Halt;
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

    private function resolverEmpresaActual(): ?Empresa
    {
        if ($tenant = Filament::getTenant()) {
            return $tenant;
        }

        $host   = request()->getHost();
        $domain = config('app.domain', '');
        $slug   = $domain ? Str::before($host, '.' . $domain) : null;

        return ($slug && $slug !== $host)
            ? Empresa::where('slug', $slug)->first()
            : null;
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
        $padre = $this->resolverEmpresaActual();

        if ($padre) {
            // Heredar identificación y configuración de la empresa padre
            $data['empresa_padre_id']              = $padre->id;
            $data['ruc']                           = $padre->ruc;
            $data['country_code']                  = $padre->country_code ?? 'PE';
            $data['logo']                          = $padre->logo;
            $data['icono']                         = $padre->icono;
            $data['descripcion']                   = $padre->descripcion;
            $data['facebook']                      = $padre->facebook;
            $data['instagram']                     = $padre->instagram;
            $data['tiktok']                        = $padre->tiktok;
            $data['modulos_activos']               = $padre->modulos_activos;
            $data['features_override']             = $padre->features_override;
            $data['fe_envio_directo_boleta']       = $padre->fe_envio_directo_boleta;
            $data['fe_envio_directo_factura']      = $padre->fe_envio_directo_factura;
            $data['impresion_comprobante_directo'] = $padre->impresion_comprobante_directo;
            $data['igv_porcentaje']                = $padre->igv_porcentaje;
            $data['rubro']                         = $padre->rubro;
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
        $padre = $this->resolverEmpresaActual();

        // Validar que el plan permita más sucursales
        if ($padre) {
            $maxSucursales    = $padre->planActual()?->maximo_locales ?? 0;
            $sucursalesActual = $padre->sucursales()->count();

            if ($sucursalesActual >= $maxSucursales) {
                Notification::make()
                    ->danger()
                    ->title('Límite de sucursales alcanzado')
                    ->body("Tu plan permite hasta {$maxSucursales} sucursal(es). Actualiza tu plan para agregar más.")
                    ->send();

                throw new Halt();
            }
        }

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

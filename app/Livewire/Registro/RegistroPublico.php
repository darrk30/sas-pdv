<?php

namespace App\Livewire\Registro;

use App\Mail\CodigoVerificacionRegistro;
use App\Models\Empresa;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\Permission\PermissionRegistrar;

#[Layout('layouts.registro')]
#[Title('Crear cuenta')]
class RegistroPublico extends Component
{
    use WithFileUploads;

    public int $paso = 1;

    // Paso 1: usuario
    public string $nombre          = '';
    public string $apellidos       = '';
    public string $email           = '';
    public string $password        = '';
    public string $passwordConfirm = '';

    // Verificación de correo
    public bool   $codigoEnviado    = false;
    public string $codigoIngresado  = '';
    #[Locked]
    public bool   $codigoVerificado = false;

    // Paso 2: plan
    public ?int   $planId = null;
    public string $ciclo  = 'mensual';

    // Paso 3: empresa (campos base)
    public string $empresaNombre    = '';
    public string $empresaRuc       = '';
    public string $empresaEmail     = '';
    public string $empresaTel       = '';
    public string $empresaDireccion = '';
    public string $empresaRubro     = '';

    // Paso 3: empresa (campos extendidos opcionales)
    public string $empresaDepartamento = '';
    public string $empresaProvincia    = '';
    public string $empresaDistrito     = '';
    public $empresaLogo = null;

    // Términos
    public bool $aceptaTerminos = false;

    // Paso 4: estado
    public string $estadoCreacion = 'pendiente';
    public string $mensajeError   = '';

    #[Locked]
    public string $redirectUrl = '';

    public function siguientePaso(): void
    {
        match ($this->paso) {
            1 => $this->validarPaso1(),
            2 => $this->validarPaso2(),
            3 => $this->validarPaso3(),
            default => null,
        };
    }

    public function enviarCodigo(): void
    {
        $this->validate([
            'nombre'   => 'required|string|max:100',
            'apellidos'=> 'required|string|max:100',
            'email'    => 'required|email|max:255|unique:users,email',
        ], [
            'nombre.required'   => 'El nombre es obligatorio.',
            'apellidos.required'=> 'Los apellidos son obligatorios.',
            'email.required'    => 'El correo es obligatorio.',
            'email.email'       => 'Ingresa un correo válido.',
            'email.unique'      => 'Este correo ya está registrado.',
        ]);

        $ratKey = 'reg-send:' . $this->email;
        if (RateLimiter::tooManyAttempts($ratKey, 5)) {
            $segundos = RateLimiter::availableIn($ratKey);
            $this->addError('email', "Enviaste demasiados códigos. Espera {$segundos} segundos e inténtalo de nuevo.");
            return;
        }
        RateLimiter::hit($ratKey, 60);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put('reg_code:' . $this->email,  hash('sha256', $code), 600);
        Cache::put('reg_tries:' . $this->email, 0,                     600);

        Mail::to($this->email)->send(new CodigoVerificacionRegistro($this->nombre, $code));

        $this->codigoEnviado    = true;
        $this->codigoVerificado = false;
        $this->codigoIngresado  = '';
    }

    public function verificarCodigo(): void
    {
        // Limpiamos el código: solo dígitos, sin espacios ni caracteres extras
        $this->codigoIngresado = preg_replace('/\D/', '', trim($this->codigoIngresado));

        $this->validate([
            'codigoIngresado' => 'required|digits:6',
        ], [
            'codigoIngresado.required' => 'Ingresa el código de 6 dígitos.',
            'codigoIngresado.digits'   => 'El código debe tener exactamente 6 dígitos.',
        ]);

        $triesKey = 'reg_tries:' . $this->email;
        $tries    = (int) Cache::get($triesKey, 0);

        if ($tries >= 5) {
            $this->addError('codigoIngresado', 'Demasiados intentos fallidos. Solicita un nuevo código.');
            $this->codigoEnviado = false;
            return;
        }

        $stored = Cache::get('reg_code:' . $this->email);

        if (! $stored) {
            $this->addError('codigoIngresado', 'El código expiró. Solicita uno nuevo.');
            $this->codigoEnviado = false;
            return;
        }

        if (! hash_equals($stored, hash('sha256', $this->codigoIngresado))) {
            Cache::put($triesKey, $tries + 1, 600);
            $restantes = 4 - $tries;
            $this->addError('codigoIngresado', "Código incorrecto. Te quedan {$restantes} intentos.");
            return;
        }

        Cache::forget('reg_code:'  . $this->email);
        Cache::forget('reg_tries:' . $this->email);
        $this->codigoVerificado = true;
        $this->codigoIngresado  = '';
    }

    public function reenviarCodigo(): void
    {
        $this->codigoEnviado    = false;
        $this->codigoVerificado = false;
        $this->codigoIngresado  = '';
        $this->enviarCodigo();
    }

    private function validarPaso1(): void
    {
        if (! $this->codigoVerificado) {
            $this->addError('codigoIngresado', 'Debes verificar tu correo antes de continuar.');
            return;
        }

        $this->validate([
            'nombre'          => 'required|string|max:100',
            'apellidos'       => 'required|string|max:100',
            'email'           => 'required|email|max:255|unique:users,email',
            'password'        => ['required', 'same:passwordConfirm',
                Password::min(8)->mixedCase()->symbols()],
            'passwordConfirm' => 'required',
        ], [
            'nombre.required'     => 'El nombre es obligatorio.',
            'apellidos.required'  => 'Los apellidos son obligatorios.',
            'email.required'      => 'El correo es obligatorio.',
            'email.unique'        => 'Este correo ya está registrado.',
            'password.required'   => 'La contraseña es obligatoria.',
            'password.same'       => 'Las contraseñas no coinciden.',
            'password.min'        => 'Mínimo 8 caracteres.',
            'password.mixed_case' => 'Debe incluir al menos una mayúscula.',
            'password.symbols'    => 'Debe incluir al menos un carácter especial (ej. @#$!).',
        ]);

        $this->paso = 2;
    }

    public function avanzarDesde2(string $ciclo): void
    {
        $this->ciclo = in_array($ciclo, ['mensual', 'anual']) ? $ciclo : 'mensual';

        $this->validate([
            'planId' => 'required|exists:plans,id,estado,activo',
            'ciclo'  => 'required|in:mensual,anual',
        ], [
            'planId.required' => 'Selecciona un plan para continuar.',
            'planId.exists'   => 'El plan seleccionado no es válido.',
        ]);

        $this->paso = 3;
    }

    private function validarPaso2(): void
    {
        $this->avanzarDesde2($this->ciclo);
    }

    private function validarPaso3(): void
    {
        $this->validate([
            'empresaNombre'       => 'required|string|max:255',
            'empresaRuc'          => 'nullable|digits:11',
            'empresaEmail'        => 'nullable|email|max:255',
            'empresaTel'          => 'nullable|string|max:20',
            'empresaDireccion'    => 'nullable|string|max:255',
            'empresaRubro'        => 'nullable|string|max:100',
            'empresaDepartamento' => 'nullable|string|max:100',
            'empresaProvincia'    => 'nullable|string|max:100',
            'empresaDistrito'     => 'nullable|string|max:100',
            'empresaLogo'         => 'nullable|image|max:2048',
            'aceptaTerminos'      => 'accepted',
        ], [
            'empresaNombre.required' => 'El nombre de la empresa es obligatorio.',
            'empresaRuc.digits'      => 'El RUC debe tener exactamente 11 dígitos.',
            'empresaEmail.email'     => 'Ingresa un correo de empresa válido.',
            'empresaLogo.image'      => 'El logo debe ser una imagen.',
            'empresaLogo.max'        => 'El logo no debe superar los 2 MB.',
            'aceptaTerminos.accepted'=> 'Debes aceptar los Términos y Condiciones para continuar.',
        ]);

        $this->paso = 4;
        $this->estadoCreacion = 'pendiente';
    }

    public function finalizarRegistro(): void
    {
        if ($this->estadoCreacion !== 'pendiente') {
            return;
        }

        $this->estadoCreacion = 'creando';

        try {
            $plan = Plan::where('id', $this->planId)->where('estado', 'activo')->firstOrFail();

            $user    = null;
            $empresa = null;

            DB::transaction(function () use ($plan, &$user, &$empresa) {
                // 1. Crear usuario (nombre completo = nombre + apellidos)
                $user = User::create([
                    'name'     => trim($this->nombre . ' ' . $this->apellidos),
                    'email'    => $this->email,
                    'password' => Hash::make($this->password),
                ]);

                // 2. Crear empresa
                $base  = Str::slug($this->empresaNombre);
                $slug  = $base;
                $count = 2;
                while (Empresa::where('slug', $slug)->exists()) {
                    $slug = $base . '-' . $count++;
                }

                $empresa = Empresa::create([
                    'name'                 => $this->empresaNombre,
                    'ruc'                  => $this->empresaRuc ?: null,
                    'email'                => $this->empresaEmail ?: null,
                    'telefono'             => $this->empresaTel ?: null,
                    'direccion'            => $this->empresaDireccion ?: null,
                    'rubro'                => $this->empresaRubro ?: null,
                    'departamento'         => $this->empresaDepartamento ?: null,
                    'provincia'            => $this->empresaProvincia ?: null,
                    'distrito'             => $this->empresaDistrito ?: null,
                    'slug'                 => $slug,
                    'estado'               => 'activo',
                    'country_code'         => 'PE',
                    'carta_activa_cliente' => 'inactivo',
                    'carta_activa_admin'   => 'inactivo',
                ]);

                // 3. Si no ingresó RUC, auto-completar con ceros + ID (11 dígitos)
                if (! $this->empresaRuc) {
                    $empresa->update(['ruc' => str_pad($empresa->id, 11, '0', STR_PAD_LEFT)]);
                }

                // 4. Subir logo si se proporcionó
                if ($this->empresaLogo) {
                    $path = $this->empresaLogo->store("logos/{$empresa->id}", 'public');
                    $empresa->update(['logo' => $path]);
                }

                // 5. Copiar módulos del plan a la empresa
                if (! empty($plan->modulos_activos)) {
                    $empresa->update(['modulos_activos' => $plan->modulos_activos]);
                }

                // 6. Crear suscripción
                $isTrial = $plan->dias_prueba_gratuita > 0;

                if ($isTrial) {
                    $suscripcion = $empresa->suscripcion()->create([
                        'plan_id'            => $plan->id,
                        'precio_pagado'      => 0,
                        'fecha_inicio'       => now(),
                        'fecha_fin'          => now()->addDays($plan->dias_prueba_gratuita),
                        'estado'             => 'activo',
                        'es_prueba_gratuita' => true,
                        'ciclo'              => 'prueba',
                    ]);

                    $suscripcion->pagos()->create([
                        'plan_id'       => $plan->id,
                        'ciclo'         => 'prueba',
                        'monto'         => 0,
                        'concepto'      => "Prueba gratuita — {$plan->dias_prueba_gratuita} días",
                        'estado'        => 'aprobado',
                        'fecha_pago'    => now(),
                        'metodo_pago'   => 'gratuito',
                        'periodo_desde' => now()->toDateString(),
                        'periodo_hasta' => now()->addDays($plan->dias_prueba_gratuita)->toDateString(),
                    ]);
                } else {
                    $esAnual = $this->ciclo === 'anual' && $plan->precio_anual !== null;

                    $empresa->suscripcion()->create([
                        'plan_id'            => $plan->id,
                        'precio_pagado'      => $esAnual ? $plan->precio_anual : $plan->precio,
                        'fecha_inicio'       => now(),
                        'fecha_fin'          => $esAnual ? now()->addYear() : now()->addMonth(),
                        'estado'             => 'activo',
                        'es_prueba_gratuita' => false,
                        'ciclo'              => $esAnual ? 'anual' : 'mensual',
                    ]);
                }

                // 7. Vincular usuario a empresa
                $empresa->usuarios()->attach($user->id, ['estado' => 'activo']);

                // 8. Asignar rol Administrador
                $role = Role::where('name', 'Administrador')
                    ->where('empresa_id', $empresa->id)
                    ->first();

                if ($role) {
                    $registrar = app(PermissionRegistrar::class);
                    $registrar->setPermissionsTeamId($empresa->id);
                    $user->assignRole($role);
                    $registrar->forgetCachedPermissions();
                }
            });

            Auth::login($user);

            $this->redirectUrl    = route('filament.pdv.pages.dashboard', ['tenant' => $empresa->slug]);
            $this->estadoCreacion = 'listo';

        } catch (\Illuminate\Database\QueryException $e) {
            $this->estadoCreacion = 'error';
            if ($e->getCode() == 23000) {
                if (str_contains($e->getMessage(), 'users')) {
                    $this->mensajeError = 'El correo electrónico ya está registrado. Intenta con otro.';
                } elseif (str_contains($e->getMessage(), 'empresas')) {
                    $this->mensajeError = 'Ya existe una empresa con ese RUC o nombre. Verifica los datos.';
                } else {
                    $this->mensajeError = 'Ya existe un registro con esos datos. Revisa e intenta de nuevo.';
                }
            } else {
                $this->mensajeError = 'Error de base de datos al crear la cuenta. Por favor intenta de nuevo.';
            }
            report($e);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->estadoCreacion = 'error';
            $this->mensajeError   = 'El plan seleccionado ya no está disponible. Vuelve al paso anterior y elige otro.';
            report($e);
        } catch (\Throwable $e) {
            $this->estadoCreacion = 'error';
            $this->mensajeError   = 'Ocurrió un error inesperado al crear tu cuenta. Por favor intenta de nuevo.';
            report($e);
        }
    }

    public function reintentar(): void
    {
        $this->paso           = 3;
        $this->estadoCreacion = 'pendiente';
        $this->mensajeError   = '';
    }

    public function planes(): \Illuminate\Database\Eloquent\Collection
    {
        return Plan::where('estado', 'activo')->orderBy('precio')->get();
    }

    public function render()
    {
        return view('livewire.registro.registro-publico', [
            'planes' => $this->planes(),
        ]);
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

/**
 * Crea el primer usuario Super Administrador de una instalación nueva.
 *
 * Sin esto, una instalación limpia en un servidor del cliente no tiene por
 * dónde entrar: los seeders de usuarios crean cuentas de demostración con
 * contraseñas conocidas, que no deben existir en producción.
 *
 *   php artisan miboleta:crear-root
 *   php artisan miboleta:crear-root --email=admin@empresa.com --nombre=Ana --apellido=Torres
 *
 * La contraseña se pide siempre por teclado y nunca se pasa por argumento: los
 * argumentos quedan en el historial del shell y en la lista de procesos.
 */
class CrearUsuarioRoot extends Command
{
    protected $signature = 'miboleta:crear-root
                            {--email= : Correo del administrador}
                            {--nombre= : Nombre}
                            {--apellido= : Apellido}';

    protected $description = 'Crea el primer usuario Super Administrador de la plataforma';

    public function handle(): int
    {
        $rolRoot = Role::where('name', 'root')->first();

        if (!$rolRoot) {
            $this->error('No existe el rol "root".');
            $this->line('Ejecuta primero:  php artisan db:seed --class=RoleSeeder --force');
            return self::FAILURE;
        }

        // Aviso, no bloqueo: puede haber una razón legítima para un segundo
        // administrador de plataforma (relevo, redundancia de acceso).
        $existentes = User::whereHas('roles', fn ($q) => $q->where('name', 'root'))->count();
        if ($existentes > 0) {
            $this->warn("Ya existen {$existentes} usuario(s) con rol root.");
            if (!$this->confirm('¿Crear otro?', false)) {
                return self::SUCCESS;
            }
        }

        $email = $this->option('email') ?: $this->ask('Correo electrónico');
        $nombre = $this->option('nombre') ?: $this->ask('Nombre');
        $apellido = $this->option('apellido') ?: $this->ask('Apellido');
        $clave = $this->secret('Contraseña');
        $claveRepetida = $this->secret('Repite la contraseña');

        if ($clave !== $claveRepetida) {
            $this->error('Las contraseñas no coinciden.');
            return self::FAILURE;
        }

        $validador = Validator::make(
            compact('email', 'nombre', 'apellido', 'clave'),
            [
                'email' => ['required', 'email', 'unique:users,email'],
                'nombre' => ['required', 'string', 'max:100'],
                'apellido' => ['required', 'string', 'max:100'],
                'clave' => ['required', Password::min(8)->letters()->numbers()],
            ],
            [
                'email.unique' => 'Ya existe un usuario con ese correo.',
                'clave.min' => 'La contraseña debe tener al menos 8 caracteres, con letras y números.',
            ]
        );

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        $usuario = User::create([
            'name' => $nombre,
            'last_name' => $apellido,
            'email' => $email,
            'password' => Hash::make($clave),
            'status' => 'active',
            // Se marca verificado: no hay a quién pedirle que confirme el correo
            // del primer administrador, y sin esto no podría entrar.
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $usuario->roles()->attach($rolRoot->id, [
            'granted_by' => $usuario->id,
            'granted_at' => now(),
        ]);

        $this->newLine();
        $this->info('Super Administrador creado.');
        $this->line("  Correo: {$usuario->email}");
        $this->newLine();
        $this->line('Ya puedes iniciar sesión y crear las empresas y sus usuarios.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\Hospital\HospitalDatabase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Instalación local en un solo paso: comprueba PostgreSQL, crea las bases que falten, migra CENTRAL, carga el
 * catálogo y la red de demostración, y prepara la base de cada hospital con datos de ejemplo.
 */
class InstallNido extends Command
{
    protected $signature = 'nido:instalar {--fresh : Borra y vuelve a crear todos los datos (¡pierde lo existente!)} {--sin-demo : Sin datos de demostración}';

    protected $description = 'Instala Hospital Nido en local: bases de datos, tablas y datos de demostración';

    public function handle(HospitalDatabase $databases): int
    {
        $this->components->info('Instalando Hospital Nido');
        $central = (string) config('database.connections.central.database');
        $hospital = (string) config('database.connections.hospital.database');

        try {
            $databases->switchTo('postgres');
            DB::connection('hospital')->scalar('SELECT 1');
        } catch (\Throwable $e) {
            $this->components->error('No se pudo conectar a PostgreSQL con los datos de .env (HOSPITAL_DB_HOST, HOSPITAL_DB_PORT, HOSPITAL_DB_USERNAME, HOSPITAL_DB_PASSWORD).');
            $this->line('  Detalle: '.$e->getMessage());

            return self::FAILURE;
        }

        foreach (array_unique([$central, $hospital]) as $name) {
            $this->components->task("Base {$name}", function () use ($name): void {
                if (! DB::connection('hospital')->scalar('SELECT 1 FROM pg_database WHERE datname = ?', [$name])) {
                    DB::connection('hospital')->statement('CREATE DATABASE "'.$name.'"');
                }
            });
        }
        $databases->reset();

        $migrate = $this->option('fresh') ? 'migrate:fresh' : 'migrate';
        $this->components->task('Tablas de CENTRAL', fn () => $this->callSilently($migrate, ['--database' => 'central', '--path' => database_path('migrations/central'), '--realpath' => true, '--force' => true]) === 0);
        $this->components->task('Sesiones, caché y colas', fn () => $this->callSilently('migrate', ['--database' => 'central', '--path' => database_path('migrations/framework'), '--realpath' => true, '--force' => true]) === 0);
        $this->components->task('Roles, permisos, hospitales y usuarios', fn () => $this->callSilently('db:seed', ['--force' => true]) === 0);
        $this->call('nido:hospitales', array_filter(['--demo' => ! $this->option('sin-demo'), '--fresh' => $this->option('fresh')]));
        $this->callSilently('cache:clear');

        $this->newLine();
        $this->components->info('Listo. Siga con: npm run build  y  php artisan serve  → http://localhost:8000');
        $this->line('  Usuarios de demostración: admin@nido.test, recepcion@nido.test, medico@nido.test, enfermeria@nido.test… · contraseña NidoDemo2026');

        return self::SUCCESS;
    }
}

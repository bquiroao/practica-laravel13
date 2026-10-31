<?php

namespace App\Console\Commands;

use App\Models\Central\Hospital;
use App\Services\Hospital\HospitalDatabase;
use Database\Seeders\DemoClinicSeeder;
use Database\Seeders\HospitalCatalogSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Crea (si falta) y migra la base clínica de cada hospital activo; con --demo carga datos de demostración. */
class PrepareHospitalDatabases extends Command
{
    protected $signature = 'nido:hospitales {--demo : Carga médicos, pacientes y citas de demostración} {--fresh : Borra y recrea el esquema de cada base}';

    protected $description = 'Prepara la base HOSPITAL de cada hospital de la red (crear, migrar y sembrar catálogos)';

    public function handle(HospitalDatabase $databases): int
    {
        $hospitals = Hospital::query()->active()->orderBy('code')->get();
        if ($hospitals->isEmpty()) {
            $this->warn('No hay hospitales activos en CENTRAL. Ejecute primero: php artisan db:seed');

            return self::FAILURE;
        }

        foreach ($hospitals as $hospital) {
            $name = $databases->databaseFor($hospital);
            $this->components->task("{$hospital->code} · {$hospital->name} → {$name}", function () use ($databases, $hospital, $name): void {
                $this->ensureDatabase($databases, $name);
                $databases->use($hospital);
                DB::connection('hospital')->statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
                $this->callSilently($this->option('fresh') ? 'migrate:fresh' : 'migrate', [
                    '--database' => 'hospital', '--path' => database_path('migrations/hospital'), '--realpath' => true, '--force' => true,
                ]);
                (new HospitalCatalogSeeder)->run();
                if ($this->option('demo')) {
                    (new DemoClinicSeeder)->runFor($hospital);
                }
            });
        }
        $databases->reset();

        return self::SUCCESS;
    }

    private function ensureDatabase(HospitalDatabase $databases, string $name): void
    {
        $databases->switchTo('postgres');
        if (! DB::connection('hospital')->scalar('SELECT 1 FROM pg_database WHERE datname = ?', [$name])) {
            DB::connection('hospital')->statement('CREATE DATABASE "'.$name.'"');
        }
    }
}

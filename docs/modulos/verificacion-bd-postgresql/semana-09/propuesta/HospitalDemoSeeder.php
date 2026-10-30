<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// Propuesta: cargar los datos de prueba de HOSPITAL desde un archivo SQL con los mismos datos que se verificaron contra PostgreSQL.
// Para adoptarla: copiar esta clase y datos-demo-hospital.sql a database/seeders/ y agregar en DatabaseSeeder::run():  $this->call(HospitalDemoSeeder::class);
class HospitalDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            return; // nunca se cargan datos de prueba en producción
        }
        DB::connection('hospital')->unprepared(file_get_contents(database_path('seeders/datos-demo-hospital.sql')));
    }
}

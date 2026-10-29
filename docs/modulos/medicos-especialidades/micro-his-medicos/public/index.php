<?php
declare(strict_types=1);

// Front controller (MVC):  php -S localhost:8080 -t public   (PDO con SQLite en la carpeta temporal o MED_DB; MED_ALMACEN=memoria usa el adaptador en memoria)
use Medicos\Aplicacion\{AsociarEspecialidad, ConsultarDisponibilidad, RegistrarMedico, Usuario};
use Medicos\Persistencia\{EnMemoria, MedicosPdo};
use Medicos\Presentacion\{Enrutador, MedicosControlador, Peticion, VistaJson};

require __DIR__ . '/../bootstrap.php';

$archivo = getenv('MED_ALMACEN') === 'memoria' ? ':memory:' : (getenv('MED_DB') ?: sys_get_temp_dir() . '/micro-his-medicos.db');
$nueva = $archivo === ':memory:' || !is_file($archivo);
$pdo = new PDO('sqlite:' . $archivo);
if ($nueva) { $pdo->exec((string) file_get_contents(__DIR__ . '/../sql/esquema.sql')); } // datos de demostración
$repo = $archivo === ':memory:' ? EnMemoria::desdePdo($pdo) : new MedicosPdo($pdo);
[$hoy, $uuid] = [fn () => date('Y-m-d'), fn () => vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4))]; // uuid, como en PostgreSQL
$usuarios = ['adm' => new Usuario('adm', 'admin'), 'rec' => new Usuario('rec', 'recepcion')]; // demostración, no son credenciales
$app = new Enrutador(new MedicosControlador(new RegistrarMedico($repo, $repo, $hoy, $uuid), new AsociarEspecialidad($repo, $repo, $uuid), new ConsultarDisponibilidad($repo, $repo, $hoy), $usuarios));
VistaJson::enviar($app->atender(Peticion::desdeGlobales()));

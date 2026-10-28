<?php
declare(strict_types=1);

// El mismo guion (camino feliz y reglas) contra los adaptadores PDO e InMemory, error de persistencia, MVC y dependencias. Datos ficticios.
require __DIR__ . '/../bootstrap.php';

use MicroHis\ContratosApi\Aplicacion\{ErrorPersistencia, EscritorVersiones, ServicioConsulta, ServicioPublicacion, ServicioRevision};
use MicroHis\ContratosApi\Dominio\VersionNueva;
use MicroHis\ContratosApi\Persistencia\{RepositorioEnMemoria, RepositorioPdo};
use MicroHis\ContratosApi\Presentacion\{ControladorDocumentacion, Rutas, VistaJson};
use function MicroHis\ContratosApi\crearControlador;

$fallos = 0;
function prueba(string $nombre, bool $ok): void { global $fallos; $fallos += $ok ? 0 : 1; echo ($ok ? 'OK    ' : 'FALLA ') . "$nombre\n"; }
function baseDePrueba(): PDO { $db = new PDO('sqlite::memory:'); $db->exec(file_get_contents(__DIR__ . '/../database/schema.sql')); return $db; }
$version = fn (string $v, array $aud = ['medico', 'enfermeria']) => ['slug' => 'guia-citas', 'tipo' => 'manual', 'version' => $v, 'audiencia' => $aud, 'usuario' => 'ana'];
$lector = fn (string $rol, ?string $v = null) => ['slug' => 'guia-citas', 'version' => $v, 'roles' => [$rol]];
$error = fn (array $r) => "{$r['status']} " . ($r['error'] ?? '');

// 1 y 2. Camino feliz y reglas de dominio: el mismo guion con cada adaptador (contrato del Repository).
foreach (['PDO' => new RepositorioPdo(baseDePrueba()), 'InMemory' => new RepositorioEnMemoria()] as $adaptador => $repo) {
    $app = crearControlador($repo);
    prueba("[$adaptador] feliz: «ana» envía guia-citas 1.0.0 = 201 en revisión y «luis» la aprueba = 200 (CA-01, CA-04)", $app->atender('enviar', $version('1.0.0')) === ['status' => 201, 'id' => 1] && $app->atender('aprobar', ['id' => 1, 'usuario' => 'luis'])['status'] === 200);
    $app->atender('enviar', $version('1.1.0', ['medico'])); $app->atender('aprobar', ['id' => 2, 'usuario' => 'luis']);
    prueba("[$adaptador] feliz: un médico ve la vigente 1.1.0 y enfermería la 1.0.0 de su audiencia (CA-05, R-06)",
           $app->atender('consultar', $lector('medico'))['version'] === '1.1.0' && $app->atender('consultar', $lector('enfermeria'))['version'] === '1.0.0');
    $app->atender('obsoleta', ['id' => 1, 'usuario' => 'luis']); $r = $app->atender('consultar', $lector('medico', '1.0.0'));
    prueba("[$adaptador] feliz: la 1.0.0 obsoleta se consulta con aviso y la vigente 1.1.0 (CA-07, R-08)", $r['status'] === 200 && $r['obsoleta'] && $r['vigente'] === '1.1.0');
    prueba("[$adaptador] regla: versión sin formato x.y.z o sin audiencia = 422 E2 (CA-02, R-01)", $error($app->atender('enviar', $version('1.2'))) === '422 E2' && $error($app->atender('enviar', $version('1.2.0', []))) === '422 E2');
    prueba("[$adaptador] regla: versión igual a la vigente = 409 E3 (CA-03, R-02)", $error($app->atender('enviar', $version('1.1.0'))) === '409 E3');
    $app->atender('enviar', $version('1.2.0'));   // id 3, de «ana»
    prueba("[$adaptador] regla: «ana» aprueba su propia versión = 403 E5 (CA-04, R-05)", $error($app->atender('aprobar', ['id' => 3, 'usuario' => 'ana'])) === '403 E5');
    prueba("[$adaptador] regla: rechazar sin motivo = 422 E2 (RF-03)", $error($app->atender('rechazar', ['id' => 3, 'usuario' => 'luis', 'motivo' => ' '])) === '422 E2');
    $fuera = $app->atender('consultar', $lector('farmacia'));
    prueba("[$adaptador] regla: rol fuera de la audiencia = 404 E6, igual que un documento inexistente (CA-06, R-07)", $error($fuera) === '404 E6' && $fuera === $app->atender('consultar', ['slug' => 'no-existe', 'roles' => ['medico']]));
    prueba("[$adaptador] regla: una versión obsoleta no vuelve a publicarse = 409 E7 (R-03, R-08)", $error($app->atender('aprobar', ['id' => 1, 'usuario' => 'luis'])) === '409 E7');
    $publicas = array_column($repo->consultables('guia-citas'), 'version'); sort($publicas);
    prueba("[$adaptador] contrato: la 1.2.0 en revisión no es consultable, una versión inexistente es null y un estado viejo devuelve false",
           $publicas === ['1.0.0', '1.1.0'] && $repo->datosRevision(99) === null && $repo->cambiarEstado(3, 'published', 'obsolete', 'luis', null) === false);
}

// 3. Error de persistencia: un doble que simula la base caída y una PDOException real.
$caida = new class implements EscritorVersiones {   // gracias al ISP, el doble implementa solo 2 métodos
    public function versiones(string $slug): array { throw new ErrorPersistencia('SQLSTATE[08006] conexión perdida'); }
    public function guardarEnRevision(VersionNueva $v, string $autor): int { return 0; }
};
$repo = new MicroHis\ContratosApi\Persistencia\RepositorioPdo(baseDePrueba());   // revisión y consulta no se usan en esta prueba
$r = (new ControladorDocumentacion(new ServicioPublicacion($caida), new ServicioRevision($repo), new ServicioConsulta($repo), new VistaJson()))->atender('enviar', $version('2.0.0'));
prueba('persistencia (doble): base caída = 503 E8 sin detalles técnicos', $error($r) === '503 E8' && !str_contains($r['mensaje'], 'SQLSTATE'));
$rota = baseDePrueba(); $rota->exec('DROP TABLE doc_audiences');
prueba('persistencia (PDO real): PDOException al guardar la audiencia = 503 E8, sin versión a medias (transacción)', $error(crearControlador(new RepositorioPdo($rota))->atender('enviar', $version('1.0.0'))) === '503 E8' && (int) $rota->query('SELECT COUNT(*) FROM doc_versions')->fetchColumn() === 0);

// 4. Entrada MVC: rutas, controlador sin SQL ni reglas, y regla de dependencias.
prueba('MVC: las rutas llevan a su acción con slug, versión o id, y una ruta desconocida responde 404',
       [Rutas::resolver('POST', '/documentos/guia-citas/versiones'), Rutas::resolver('POST', '/versiones/3/rechazo'), Rutas::resolver('GET', '/documentos/guia-citas/versiones/1.0.0')]
       === [['enviar', ['slug' => 'guia-citas']], ['rechazar', ['id' => 3]], ['consultar', ['slug' => 'guia-citas', 'version' => '1.0.0']]]
       && Rutas::resolver('DELETE', '/versiones/3') === ['', []] && crearControlador(new RepositorioEnMemoria())->atender('', [])['status'] === 404);
prueba('MVC: el controlador no tiene SQL, PDO, versiones, audiencias ni throw', !preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b|PDO|VersionNueva|Audiencia|throw /', file_get_contents(__DIR__ . '/../src/Presentacion/ControladorDocumentacion.php')));
$haciaAfuera = [];
foreach (['Dominio' => ['Aplicacion', 'Persistencia', 'Presentacion'], 'Aplicacion' => ['Persistencia', 'Presentacion'], 'Persistencia' => ['Presentacion'], 'Presentacion' => ['Persistencia']] as $capa => $prohibidas)
    foreach ($prohibidas as $p) if (str_contains(implode('', array_map('file_get_contents', glob(__DIR__ . "/../src/$capa/*.php"))), "MicroHis\\ContratosApi\\$p")) $haciaAfuera[] = "$capa -> $p";
prueba('dependencias: el Dominio no usa otra capa, Aplicación solo usa Dominio, Persistencia y Presentación no se usan entre sí', $haciaAfuera === []);
prueba('PDO: ninguna consulta concatena variables en el SQL', !preg_match('/(prepare|query|exec)\(\s*"[^"]*\$/', file_get_contents(__DIR__ . '/../src/Persistencia/RepositorioPdo.php')));

echo $fallos === 0 ? "RESULTADO: todo correcto\n" : "RESULTADO: $fallos fallos\n";
exit($fallos === 0 ? 0 : 1);

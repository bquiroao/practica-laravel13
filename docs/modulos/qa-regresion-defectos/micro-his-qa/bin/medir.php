<?php

declare(strict_types=1);

/*
 * Semana 5 · Medición para decidir la frontera de microservicio. Registra N resultados FALLA (con defecto y una captura
 * de 200 KB distinta cada vez) en:
 *   A · en proceso, memoria                         B · en proceso, PostgreSQL + almacén en disco (el monolito de hoy)
 *   C · por HTTP (php -S) + PostgreSQL, con ClienteQa (QA como servicio para su cliente)
 *   D · en proceso + PostgreSQL, con el ALMACÉN como servicio aparte (php -S), por AlmacenEvidenciasHttp
 *   E · como D, con el almacén caído (500): cuántos se registran y cuánto tarda en decir que no
 *   F · como C, con una captura de 2 MB (el costo de mandar evidencias por JSON/base64)
 * Uso: QA_DB_DSN=… php bin/medir.php [N=100]      (base de prueba: se recrea el esquema «qa»)
 */

require dirname(__DIR__) . '/bootstrap/contenedor.php';
require __DIR__ . '/instalar-base.php';

use Qa\Cliente\ClienteQa;
use Qa\Cliente\ResultadoCliente;
use Qa\Cliente\TransporteHttpStream;
use Qa\Persistencia\Configuracion\RelojSistema;
use Qa\Persistencia\FabricaAdaptadores;
use Qa\Persistencia\Http\AlmacenEvidenciasHttp;
use Qa\Persistencia\Http\CircuitoInterruptor;
use Qa\Persistencia\Pdo\FabricaConexion;

$n = (int) ($argv[1] ?? 100);
getenv('QA_DB_DSN') ?: exit("Defina QA_DB_DSN (base de prueba: se recrea el esquema).\n");
$config = ['registro_acceso' => 'ninguno', 'persistencia' => 'pdo', 'evidencias' => sys_get_temp_dir() . '/qa-medir-' . getmypid()]
    + (require dirname(__DIR__) . '/config/config.php');

/** @return array{0: resource, 1: string} */
function servidor(string $router, array $entorno, ?string $docroot = null): array
{
    $puerto = 19000 + random_int(0, 999);
    $args = [PHP_BINARY, '-S', "127.0.0.1:$puerto", ...($docroot !== null ? ['-t', $docroot] : []), $router];
    $proceso = proc_open($args, [['pipe', 'r'], ['file', 'NUL', 'w'], ['file', 'NUL', 'w']], $t, dirname(__DIR__), $entorno + getenv());
    for ($i = 0; $i < 50 && @fsockopen('127.0.0.1', $puerto) === false; $i++) {
        usleep(100_000);
    }

    return [$proceso, "http://127.0.0.1:$puerto"];
}

/** @param callable(int): bool $una devuelve true si registró @return array{p50: float, p95: float, ok: int} */
function medir(int $n, callable $una): array
{
    $tiempos = [];
    $ok = 0;
    for ($i = 0; $i < $n; $i++) {
        $t = hrtime(true);
        $ok += $una($i) ? 1 : 0;
        $tiempos[] = (hrtime(true) - $t) / 1e6;
    }
    sort($tiempos);

    return ['p50' => $tiempos[(int) floor(0.5 * ($n - 1))], 'p95' => $tiempos[(int) floor(0.95 * ($n - 1))], 'ok' => $ok];
}

function captura(int $i, int $bytes): string
{
    return "\x89PNG\r\n\x1a\n" . str_pad("captura-$i-", $bytes - 8, random_bytes(16));
}

/** Registra en proceso con los puertos dados. */
function enProceso(array $p, int $n, int $tamano): array
{
    $uc = construirCasosDeUso($p);
    $c = $uc['planificar']->ejecutar('lider-qa', 'hosp-norte', ['nombre' => 'Medición', 'funcionalidades' => ['expediente'], 'casos' => ['TC-107', 'TC-108']]);
    $uc['iniciar']->ejecutar('lider-qa', 'hosp-norte', $c->datos['id'], '1.4.0');

    return medir($n, static fn (int $i): bool => $uc['registrar']->ejecutar('tito-tester', 'hosp-norte', $c->datos['id'], sprintf('med-%s-%06d', bin2hex(random_bytes(3)), $i), [
        'caso' => 'TC-107', 'resultado' => 'FALLA', 'evidencias' => [['tipo' => 'captura', 'bytes' => captura($i, $tamano)]],
        'defecto' => ['titulo' => 'Medición', 'pasos' => ['Ejecutar'], 'severidad' => 'MAYOR'],
    ])->codigo === 'RESULTADO_REGISTRADO');
}

function pdoNuevo(array $config): \PDO
{
    $pdo = FabricaConexion::crear((string) getenv('QA_DB_DSN'), $config['db']['usuario'], $config['db']['clave']);
    instalarBase($pdo);

    return $pdo;
}

function porHttp(string $url, int $n, int $tamano): array
{
    $lider = new ClienteQa(new TransporteHttpStream($url), 'token-lider-qa', 'hosp-norte');
    $id = $lider->planificar('Medición', ['expediente'], ['TC-107', 'TC-108'])->cuerpo['id'];
    $lider->iniciar($id, '1.4.0');
    $tester = new ClienteQa(new TransporteHttpStream($url, 10.0), 'token-tito-tester', 'hosp-norte');

    return medir($n, static fn (int $i): bool => $tester->registrarResultado($id, 'TC-107', 'FALLA', [['tipo' => 'captura', 'bytes' => captura($i, $tamano)]],
        ['titulo' => 'Medición', 'pasos' => ['Ejecutar'], 'severidad' => 'MAYOR'])->tipo === ResultadoCliente::CONFIRMADO);
}

$kb200 = 200 * 1024;
$r = [];
$r['A · en proceso, memoria'] = enProceso(FabricaAdaptadores::memoria($config, require dirname(__DIR__) . '/database/datos-demo.php'), $n, $kb200);
$r['B · en proceso, PostgreSQL + almacén en disco'] = enProceso(FabricaAdaptadores::crear($config, pdoNuevo($config)), $n, $kb200);

$entornoApi = ['QA_PERSISTENCIA' => 'pdo', 'QA_REGISTRO_ACCESO' => 'ninguno', 'QA_EVIDENCIAS_DIR' => $config['evidencias']];
pdoNuevo($config);
[$api, $url] = servidor('public/index.php', $entornoApi, 'public');
try {
    $r['C · por HTTP (php -S) + PostgreSQL, con ClienteQa'] = porHttp($url, $n, $kb200);
} finally {
    proc_terminate($api);
}

[$alm, $urlAlm] = servidor('bin/almacen-simulado.php', ['ALMACEN_DIR' => $config['evidencias'] . '-remoto']);
try {
    $p = FabricaAdaptadores::crear($config, pdoNuevo($config));
    $p['almacen'] = new AlmacenEvidenciasHttp($urlAlm, new CircuitoInterruptor(new RelojSistema()));
    $r['D · en proceso + PostgreSQL, almacén REMOTO (HTTP)'] = enProceso($p, $n, $kb200);
} finally {
    proc_terminate($alm);
}

[$caido, $urlCaido] = servidor('bin/almacen-simulado.php', ['ALMACEN_ESTADO' => '500']);
try {
    $p = FabricaAdaptadores::crear($config, pdoNuevo($config));
    $p['almacen'] = new AlmacenEvidenciasHttp($urlCaido, new CircuitoInterruptor(new RelojSistema()));
    $r['E · como D, con el almacén caído (500)'] = enProceso($p, $n, $kb200);
} finally {
    proc_terminate($caido);
}

pdoNuevo($config);
[$api2, $url2] = servidor('public/index.php', $entornoApi, 'public');
try {
    $r['F · como C, captura de 2 MB'] = porHttp($url2, max(10, intdiv($n, 5)), 2 * 1024 * 1024);
} finally {
    proc_terminate($api2);
}

printf("Resultados FALLA con defecto y una captura de 200 KB por escenario: %d (PHP %s)\n\n", $n, PHP_VERSION);
foreach ($r as $nombre => $m) {
    printf("%-52s p50=%9.3f ms   p95=%9.3f ms   registrados=%d/%d\n", $nombre, $m['p50'], $m['p95'], $m['ok'], str_starts_with($nombre, 'F') ? max(10, intdiv($n, 5)) : $n);
}
$v = array_values($r);
printf("\nCosto de la base (B − A): %.3f ms · de la API por HTTP (C − B): %.3f ms · del almacén remoto (D − B): %.3f ms · de 2 MB por la API (F − C): %.3f ms\n",
    $v[1]['p50'] - $v[0]['p50'], $v[2]['p50'] - $v[1]['p50'], $v[3]['p50'] - $v[1]['p50'], $v[5]['p50'] - $v[2]['p50']);

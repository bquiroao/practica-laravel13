<?php

declare(strict_types=1);

/*
 * Semana 5 · Medición para decidir la frontera de microservicio. Reporta N resultados FALLA (con una traza de 200 KB
 * distinta cada vez, que es lo que un E2E real adjunta) en:
 *   A · en proceso, memoria
 *   B · en proceso, PostgreSQL + almacén en disco                       (el monolito modular de hoy, sin HTTP)
 *   C · por HTTP (php -S) + PostgreSQL, con ReportadorCi                 (lo que hace el pipeline hoy)
 *   D · como C, pero pasando por un «servicio de ingesta» aparte         (la frontera candidata: un salto más)
 *   E · como D, con la API caída: cuánto tarda la ingesta en decir que no y qué le queda al pipeline
 * Uso: E2E_DB_DSN=… php bin/medir.php [N=100]      (base de prueba: se recrea el esquema «e2e»)
 */

require dirname(__DIR__) . '/bootstrap/contenedor.php';
require __DIR__ . '/instalar-base.php';

use E2e\Cliente\BandejaPendientes;
use E2e\Cliente\ReportadorCi;
use E2e\Cliente\TransporteHttpStream;
use E2e\Persistencia\FabricaAdaptadores;
use E2e\Persistencia\Pdo\FabricaConexion;

$n = (int) ($argv[1] ?? 100);
$dsn = getenv('E2E_DB_DSN') ?: exit("Defina E2E_DB_DSN (base de prueba: se recrea el esquema).\n");
$config = ['registro_acceso' => 'ninguno', 'persistencia' => 'pdo', 'evidencias' => sys_get_temp_dir() . '/e2e-medir-' . getmypid()]
    + (require dirname(__DIR__) . '/config/config.php');
$digest = 'sha256:' . hash('sha256', 'medicion');
$traza = static fn (int $i): string => "traza playwright $i\n" . str_pad('', 200 * 1024, hash('sha256', (string) $i));

/** @return array{0: resource, 1: string} */
function servidor(string $router, array $entorno, int $puerto): array
{
    $p = proc_open([PHP_BINARY, '-S', "127.0.0.1:$puerto", $router], [['pipe', 'r'], ['file', 'NUL', 'w'], ['file', 'NUL', 'w']], $t, dirname(__DIR__) . '/public',
        $entorno + getenv());
    for ($i = 0; $i < 50 && @fsockopen('127.0.0.1', $puerto) === false; $i++) {
        usleep(100_000);
    }

    return [$p, "http://127.0.0.1:$puerto"];
}

/** @param callable(int): bool $una @return array{p50: float, p95: float, ok: int} */
function medir(int $n, callable $una): array
{
    $t = [];
    $ok = 0;
    for ($i = 0; $i < $n; $i++) {
        $inicio = hrtime(true);
        $ok += $una($i) ? 1 : 0;
        $t[] = (hrtime(true) - $inicio) / 1e6;
    }
    sort($t);

    return ['p50' => $t[(int) floor(0.5 * ($n - 1))], 'p95' => $t[(int) floor(0.95 * ($n - 1))], 'ok' => $ok];
}

/** Una versión con un plan de N escenarios (todos no críticos) y una ejecución en curso; devuelve el id de la ejecución. */
function preparar(array $uc, int $n, string $digest): int
{
    for ($i = 0; $i < $n; $i++) {
        $uc['escenario']->ejecutar('ing-qa', 'hosp-norte', ['id' => sprintf('E2E-%04d', 1000 + $i), 'titulo' => "Medición $i", 'modulo' => 'medicion',
            'critico' => false, 'requisitos' => ['RF-01'], 'datos' => 'SINT-MEDICION']);
    }
    $uc['version']->ejecutar('pipeline-ci', 'hosp-norte', ['numero' => '9.0.0', 'commit' => 'abcdef1', 'digest' => $digest, 'modulos' => ['medicion']]);
    $todos = array_map(static fn (int $i): string => sprintf('E2E-%04d', 1000 + $i), range(0, $n - 1));
    $uc['plan']->ejecutar('ing-qa', 'hosp-norte', '9.0.0', ['E2E-101', 'E2E-102', ...$todos]);

    return $uc['iniciar']->ejecutar('pipeline-ci', 'hosp-norte', '9.0.0', $digest, 'staging')->datos['id'];
}

$enProceso = static function (array $p) use ($n, $digest, $traza, $config): array {
    $uc = construirCasosDeUso($p, $config['politica_gate']);
    $id = preparar($uc, $n, $digest);

    return medir($n, static fn (int $i): bool => $uc['reportar']->ejecutar('pipeline-ci', 'hosp-norte', $id, sprintf('medir-%06d', $i), ['escenario' => sprintf('E2E-%04d', 1000 + $i),
        'resultado' => 'FALLA', 'intentos' => 1, 'evidencias' => [['tipo' => 'traza', 'bytes' => $traza($i)]]])->codigo === 'RESULTADO_REPORTADO');
};
$porHttp = static function (string $url, int $id, string $prefijo) use ($n, $traza): array {
    $r = new ReportadorCi(new TransporteHttpStream($url, 10.0), 'token-pipeline', 'hosp-norte', new BandejaPendientes(sys_get_temp_dir() . "/e2e-medir-$prefijo.jsonl"), 1);

    return medir($n, static fn (int $i): bool => $r->reportar($id, sprintf('E2E-%04d', 1000 + $i), 'FALLA', 1, [['tipo' => 'traza', 'bytes' => $traza($i)]],
        sprintf('medir-%s-%06d', $prefijo, $i))['estado'] === ReportadorCi::CONFIRMADO);
};
$pdoNuevo = static function () use ($dsn, $config): PDO {
    $pdo = FabricaConexion::crear($dsn, $config['db']['usuario'], $config['db']['clave']);
    instalarBase($pdo);

    return $pdo;
};

$r = [];
$r['A · en proceso, memoria'] = $enProceso(FabricaAdaptadores::memoria($config, require dirname(__DIR__) . '/database/datos-demo.php'));
$r['B · en proceso, PostgreSQL + disco'] = $enProceso(FabricaAdaptadores::crear($config, $pdoNuevo()));

$entorno = ['E2E_PERSISTENCIA' => 'pdo', 'E2E_REGISTRO_ACCESO' => 'ninguno', 'E2E_EVIDENCIAS_DIR' => $config['evidencias'], 'E2E_DB_DSN' => $dsn];
$id = preparar(construirCasosDeUso(FabricaAdaptadores::crear($config, $pdoNuevo()), $config['politica_gate']), $n, $digest);
[$api, $urlApi] = servidor('index.php', $entorno, 8092);
[$ingesta, $urlIngesta] = servidor(dirname(__DIR__) . '/bin/proxy-ingesta.php', ['E2E_API' => $urlApi], 8093);
try {
    $r['C · por HTTP + PostgreSQL (hoy)'] = $porHttp($urlApi, $id, 'c');
    $id = preparar(construirCasosDeUso(FabricaAdaptadores::crear($config, $pdoNuevo()), $config['politica_gate']), $n, $digest);
    $r['D · ingesta aparte → API → PostgreSQL'] = $porHttp($urlIngesta, $id, 'd');
    proc_terminate($api);
    usleep(300_000);
    $r['E · ingesta aparte con la API caída'] = $porHttp($urlIngesta, $id, 'e');
} finally {
    proc_terminate($ingesta);
    @proc_terminate($api);
}

printf("Reportar un resultado FALLA con una traza de 200 KB · N = %d\n\n%-42s %9s %9s %12s\n", $n, 'Escenario', 'p50 ms', 'p95 ms', 'confirmados');
foreach ($r as $nombre => $m) {
    printf("%-42s %9.1f %9.1f %8d/%d\n", $nombre, $m['p50'], $m['p95'], $m['ok'], $n);
}
$c = $r['C · por HTTP + PostgreSQL (hoy)'];
$d = $r['D · ingesta aparte → API → PostgreSQL'];
printf("\nCosto del salto extra (D − C): p50 %+.1f ms, p95 %+.1f ms por resultado; en un job de 300 escenarios: %+.1f s.\n", $d['p50'] - $c['p50'], $d['p95'] - $c['p95'],
    300 * ($d['p50'] - $c['p50']) / 1000);

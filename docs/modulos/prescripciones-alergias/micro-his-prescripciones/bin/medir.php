<?php

declare(strict_types=1);

/*
 * Semana 5 · Medición para decidir la frontera de microservicio. Emite N prescripciones (P-005, paracetamol) en:
 *   A · en proceso, memoria                       B · en proceso, PostgreSQL
 *   C · por HTTP (php -S) + PostgreSQL, con ClientePrescripciones
 *   D · en proceso + PostgreSQL, pero el Expediente es un SERVICIO aparte (php -S) leído con HttpFuenteAlergias
 *   E · como D, con el Expediente caído (500): cuántas se emiten y cuánto tarda en decir que no
 * Uso: PRESCRIPCIONES_DB_DSN=… php bin/medir.php [N=200]
 */

require dirname(__DIR__) . '/bootstrap/contenedor.php';

use Prescripciones\Cliente\ClientePrescripciones;
use Prescripciones\Cliente\ResultadoCliente;
use Prescripciones\Cliente\TransporteHttpStream;
use Prescripciones\Dominio\Modelo\SolicitudEmision;
use Prescripciones\Persistencia\Configuracion\RelojSistema;
use Prescripciones\Persistencia\FabricaAdaptadores;
use Prescripciones\Persistencia\Http\CircuitoInterruptor;
use Prescripciones\Persistencia\Http\HttpFuenteAlergias;
use Prescripciones\Persistencia\Pdo\FabricaConexion;

$n = (int) ($argv[1] ?? 200);
$config = require dirname(__DIR__) . '/config/config.php';
$config['registro_acceso'] = 'ninguno';
getenv('PRESCRIPCIONES_DB_DSN') ?: exit("Defina PRESCRIPCIONES_DB_DSN (base de prueba: se recrea el esquema).\n");
$item = [['medicamento' => 'PARA500', 'dosis' => 500, 'unidad' => 'mg', 'frecuencia_horas' => 8, 'via' => 'oral', 'dias' => 5]];

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

/** @param callable(int): bool $una devuelve true si emitió @return array{p50: float, p95: float, emitidas: int} */
function medir(int $n, callable $una): array
{
    $tiempos = [];
    $emitidas = 0;
    for ($i = 0; $i < $n; $i++) {
        $t = hrtime(true);
        $emitidas += $una($i) ? 1 : 0;
        $tiempos[] = (hrtime(true) - $t) / 1e6;
    }
    sort($tiempos);

    return ['p50' => $tiempos[(int) floor(0.5 * ($n - 1))], 'p95' => $tiempos[(int) floor(0.95 * ($n - 1))], 'emitidas' => $emitidas];
}

$instalar = static function () use ($config): \PDO {
    $pdo = FabricaConexion::crear($config['db']['dsn'], $config['db']['usuario'], $config['db']['clave']);
    $pdo->exec((string) file_get_contents(dirname(__DIR__) . '/database/schema.sql'));
    $pdo->exec((string) file_get_contents(dirname(__DIR__) . '/database/datos-demo.sql'));

    return $pdo;
};
$enProceso = static function (array $puertos, string $prefijo) use ($item): callable {
    $emitir = construirCasosDeUso($puertos)['emitir'];

    return static fn (int $i): bool => $emitir->ejecutar(new SolicitudEmision('dr-lopez', 'hosp-norte', 'P-005', "$prefijo-$i", $item))->codigo === 'EMITIDA';
};

$resultados = [];
$resultados['A · en proceso, memoria'] = medir($n, $enProceso(FabricaAdaptadores::memoria(require dirname(__DIR__) . '/database/datos-demo.php', $config), 'a'));
$resultados['B · en proceso, PostgreSQL'] = medir($n, $enProceso(FabricaAdaptadores::pdo($instalar(), $config), 'b'));

[$app, $urlApp] = servidor(dirname(__DIR__) . '/public/index.php', ['PRESCRIPCIONES_REGISTRO_ACCESO' => 'ninguno'], dirname(__DIR__) . '/public');
$cliente = new ClientePrescripciones(new TransporteHttpStream($urlApp), 'token-dr-lopez', 'hosp-norte');
$resultados['C · por HTTP (php -S) + PostgreSQL, con el cliente'] = medir($n,
    static fn (int $i): bool => $cliente->emitir('P-005', $item)->tipo === ResultadoCliente::CONFIRMADO);
proc_terminate($app);

[$exp, $urlExp] = servidor(dirname(__DIR__) . '/bin/expediente-simulado.php', []);
$remoto = FabricaAdaptadores::pdo($instalar(), $config);
$remoto['alergias'] = new HttpFuenteAlergias($urlExp, new CircuitoInterruptor(new RelojSistema()));
$resultados['D · en proceso + PostgreSQL, Expediente remoto (HTTP)'] = medir($n, $enProceso($remoto, 'd'));
proc_terminate($exp);

[$caido, $urlCaido] = servidor(dirname(__DIR__) . '/bin/expediente-simulado.php', ['EXPEDIENTE_ESTADO' => '500']);
$conCaido = FabricaAdaptadores::pdo($instalar(), $config);
$conCaido['alergias'] = new HttpFuenteAlergias($urlCaido, new CircuitoInterruptor(new RelojSistema()));
$resultados['E · como D, con el Expediente caído (500)'] = medir($n, $enProceso($conCaido, 'e'));
proc_terminate($caido);

printf("Emisiones por escenario: %d (PHP %s)\n\n", $n, PHP_VERSION);
foreach ($resultados as $nombre => $r) {
    printf("%-56s p50=%8.3f ms   p95=%8.3f ms   emitidas=%d/%d\n", $nombre, $r['p50'], $r['p95'], $r['emitidas'], $n);
}
$a = $resultados['A · en proceso, memoria'];
$b = $resultados['B · en proceso, PostgreSQL'];
$c = $resultados['C · por HTTP (php -S) + PostgreSQL, con el cliente'];
$d = $resultados['D · en proceso + PostgreSQL, Expediente remoto (HTTP)'];
printf("\nCosto de la base (B − A): %.3f ms · Costo de la API por HTTP (C − B): %.3f ms · Costo del Expediente remoto (D − B): %.3f ms\n",
    $b['p50'] - $a['p50'], $c['p50'] - $b['p50'], $d['p50'] - $b['p50']);

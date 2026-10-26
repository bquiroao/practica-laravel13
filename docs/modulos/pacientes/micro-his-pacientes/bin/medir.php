<?php

declare(strict_types=1);

/*
 * Mide cuánto cuesta consultar un paciente según dónde viva el módulo, para decidir la
 * frontera de microservicio con datos y no con preferencias.
 *   php bin/medir.php [n_en_proceso=5000] [n_por_red=300]
 *
 *  A)  caso de uso en el mismo proceso (módulo dentro del HIS)
 *  B)  API HTTP completa en el mismo proceso (Kernel, sin red)
 *  C)  servicio separado por red local (php -S + ClientePacientes + TransporteHttpStream)
 *  C0) control: petición mínima (404 de ruta) por la misma red
 *  D)  servicio separado con caché del cliente (If-None-Match → 304)
 */

use Pacientes\Aplicacion\Comandos\ComandoConsultar;
use Pacientes\Cliente\CircuitoInterruptor;
use Pacientes\Cliente\ClientePacientes;
use Pacientes\Cliente\PeticionHttp;
use Pacientes\Cliente\ResultadoCliente;
use Pacientes\Cliente\TransporteHttpStream;
use Pacientes\Presentacion\Http\Solicitud;

require dirname(__DIR__) . '/bootstrap/contenedor.php';

$nLocal = (int) ($argv[1] ?? 5000);
$nRed = (int) ($argv[2] ?? 300);
$config = ['persistencia' => 'memoria'] + (require dirname(__DIR__) . '/config/config.php');
$cabeceras = ['X-Tenant-ID' => 'hosp-oriente', 'Authorization' => 'Bearer token-clinico-oriente'];

/** @return array{n: int, p50: float, p95: float, p99: float} en milisegundos */
function medir(int $n, Closure $operacion): array
{
    $operacion(); // calentamiento
    $tiempos = [];
    for ($i = 0; $i < $n; $i++) {
        $inicio = hrtime(true);
        $operacion();
        $tiempos[] = (hrtime(true) - $inicio) / 1e6;
    }
    sort($tiempos);
    $p = static fn (float $q): float => $tiempos[(int) min($n - 1, floor($q * $n))];

    return ['n' => $n, 'p50' => $p(0.50), 'p95' => $p(0.95), 'p99' => $p(0.99)];
}

$resultados = [];
$errores = 0;

// A y B: en proceso (los mismos casos de uso y datos de demostración)
$casos = crearCasosDeUso($config);
$kernel = construirKernel($casos, new Pacientes\Presentacion\Http\Autenticacion\AutenticadorTokenDemo($config['tokens_demo']));
$resultados['A) Módulo en proceso (caso de uso)'] = medir($nLocal, static function () use ($casos, &$errores): void {
    $errores += $casos['consultar']->ejecutar(new ComandoConsultar('clinico-01', 'hosp-oriente', 'P-000001'))->exitoso() ? 0 : 1;
});
$resultados['B) API HTTP en proceso (Kernel, sin red)'] = medir($nLocal, static function () use ($kernel, $cabeceras, &$errores): void {
    $errores += $kernel->manejar(new Solicitud('GET', '/api/v1/pacientes/P-000001', $cabeceras))->estado === 200 ? 0 : 1;
});

// C, C0 y D: servidor separado por red local
$puerto = 8090 + random_int(0, 9);
$entorno = getenv() + ['PACIENTES_PERSISTENCIA' => 'memoria'];
$nulo = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
$servidor = proc_open([PHP_BINARY, '-S', "127.0.0.1:$puerto", '-t', dirname(__DIR__) . '/public'], [
    0 => ['pipe', 'r'], 1 => ['file', $nulo, 'w'], 2 => ['file', $nulo, 'w'],
], $tuberias, null, $entorno);
if ($servidor === false) {
    fwrite(STDERR, "No se pudo iniciar php -S\n");
    exit(1);
}
usleep(700_000);

try {
    $transporte = new TransporteHttpStream("http://127.0.0.1:$puerto");
    $sinCache = new ClientePacientes($transporte, new CircuitoInterruptor(), 'token-clinico-oriente', 'hosp-oriente', timeoutSegundos: 2.0);
    $resultados['C) Servicio separado por red local'] = medir($nRed, static function () use ($transporte, $cabeceras, &$errores): void {
        $errores += $transporte->enviar(new PeticionHttp('GET', '/api/v1/pacientes/P-000001', $cabeceras), 2.0)->estado === 200 ? 0 : 1;
    });
    $resultados['C0) Control: 404 mínimo por la misma red'] = medir($nRed, static function () use ($transporte, $cabeceras, &$errores): void {
        $errores += $transporte->enviar(new PeticionHttp('GET', '/no-existe', $cabeceras), 2.0)->estado === 404 ? 0 : 1;
    });
    $sinCache->consultar('P-000001'); // llena la caché del cliente
    $resultados['D) Servicio separado + caché ETag (304)'] = medir($nRed, static function () use ($sinCache, &$errores): void {
        $errores += $sinCache->consultar('P-000001')->origen === ResultadoCliente::CACHE_VALIDADA ? 0 : 1;
    });
} finally {
    proc_terminate($servidor);
}

$base = $resultados['A) Módulo en proceso (caso de uso)']['p50'];
echo 'Micro-HIS Pacientes - medicion de la frontera (PHP ' . PHP_VERSION . ', ' . PHP_OS_FAMILY . ', adaptadores InMemory)' . PHP_EOL;
echo str_pad('Escenario', 44) . str_pad('n', 7, ' ', STR_PAD_LEFT) . str_pad('p50 ms', 11, ' ', STR_PAD_LEFT)
    . str_pad('p95 ms', 11, ' ', STR_PAD_LEFT) . str_pad('p99 ms', 11, ' ', STR_PAD_LEFT) . str_pad('x p50 de A', 13, ' ', STR_PAD_LEFT) . PHP_EOL;
foreach ($resultados as $nombre => $r) {
    echo str_pad($nombre, 44) . str_pad((string) $r['n'], 7, ' ', STR_PAD_LEFT)
        . str_pad(number_format($r['p50'], 3), 11, ' ', STR_PAD_LEFT) . str_pad(number_format($r['p95'], 3), 11, ' ', STR_PAD_LEFT)
        . str_pad(number_format($r['p99'], 3), 11, ' ', STR_PAD_LEFT) . str_pad(number_format($r['p50'] / $base, 0) . 'x', 13, ' ', STR_PAD_LEFT) . PHP_EOL;
}
echo 'Respuestas incorrectas: ' . $errores . PHP_EOL;

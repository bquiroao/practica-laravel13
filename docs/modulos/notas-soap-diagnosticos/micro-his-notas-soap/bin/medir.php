<?php

declare(strict_types=1);

/*
 * Mide cuánto cuesta registrar una nota según dónde viva el módulo. Datos para decidir la frontera.
 *   php bin/medir.php [n_en_proceso=2000] [n_red=200]
 * Usa los adaptadores en memoria (no mide la base de datos, mide el costo de la frontera).
 *   A) caso de uso llamado en proceso (monolito modular)
 *   B) API completa en proceso: Kernel + controlador + vistas, sin red
 *   C) servicio separado por red local: php -S + ClienteNotasSoap (TransporteHttpStream)
 *   D) C más 2 consultas remotas previas, como si Expediente y Asignaciones también fueran servicios
 *   C0) control: petición mínima (404) por la misma red, para separar el costo de la red del de la lógica
 */

use NotasSoap\Aplicacion\CasosDeUso\RegistrarNotaConDiagnostico;
use NotasSoap\Aplicacion\Comandos\RegistrarNotaComando;
use NotasSoap\Cliente\CircuitoInterruptor;
use NotasSoap\Cliente\ClienteNotasSoap;
use NotasSoap\Cliente\TransporteHttpStream;
use NotasSoap\Persistencia\EnMemoria\AlmacenesEnMemoria;
use NotasSoap\Persistencia\Sistema\GeneradorUuid;
use NotasSoap\Persistencia\Sistema\RelojSistema;
use NotasSoap\Presentacion\Http\Solicitud;

require dirname(__DIR__) . '/bootstrap/contenedor.php';

$nProceso = (int) ($argv[1] ?? 2000);
$nRed = (int) ($argv[2] ?? 200);
$raiz = dirname(__DIR__);
$config = require $raiz . '/config/config.php';
$datos = require $raiz . '/config/datos-demo-memoria.php';
$nota = json_decode((string) file_get_contents($raiz . '/ejemplos/nota.json'), true);

/** @param list<float> $ms */
$resumen = static function (string $nombre, array $ms, int $errores) : array {
    sort($ms);
    $p = static fn (float $q): float => $ms[(int) min(count($ms) - 1, floor($q * (count($ms) - 1)))];

    return [$nombre, count($ms), $p(0.50), $p(0.95), $p(0.99), $errores];
};
$cronometrar = static function (int $n, callable $una): array {
    $ms = [];
    $errores = 0;
    for ($i = 0; $i < $n; $i++) {
        $t = hrtime(true);
        $ok = $una($i);
        $ms[] = (hrtime(true) - $t) / 1e6;
        $errores += $ok ? 0 : 1;
    }

    return [$ms, $errores];
};
$filas = [];

// A) caso de uso en proceso
$al = new AlmacenesEnMemoria($datos);
$a = $al->conjunto();
$uc = new RegistrarNotaConDiagnostico($a->acceso, $a->expedientes, $a->notas, $a->diagnosticos, $a->auditoria, $a->transaccion, new GeneradorUuid(), new RelojSistema(), $a->idempotencia);
$cmd = new RegistrarNotaComando('dra-mendez', 'hosp-oriente', ['medico'], 'hosp-oriente', 'exp-carlos', 'epi-100',
    $nota['subjetivo'], $nota['objetivo'], $nota['analisis'], $nota['plan'], 'J18.9', 'Neumonía no especificada', 'primary');
[$ms, $err] = $cronometrar($nProceso, static fn (): bool => $uc->ejecutar($cmd)->notaId !== '');
$filas[] = $resumen('A) Caso de uso en proceso (monolito modular)', $ms, $err);

// B) API completa en proceso
$kernel = crearKernelEnMemoria($config, new AlmacenesEnMemoria($datos), new GeneradorUuid(), new RelojSistema());
$cab = ['x-tenant-id' => 'hosp-oriente', 'authorization' => 'Bearer token-mendez'];
[$ms, $err] = $cronometrar($nProceso, static fn (): bool => $kernel->manejar(new Solicitud('POST', '/api/v1/notas-soap', $cab, $nota))->estado === 201);
$filas[] = $resumen('B) API HTTP en proceso (Kernel completo, sin red)', $ms, $err);

// Levantar el servidor en modo memoria
$puerto = 18000 + random_int(0, 999);
$nulo = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
$proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$puerto", '-t', $raiz . '/public'], [1 => ['file', $nulo, 'w'], 2 => ['file', $nulo, 'w']], $tuberias, null,
    ['NOTASSOAP_ALMACEN' => 'memoria'] + getenv());
$url = "http://127.0.0.1:$puerto";
for ($i = 0; $i < 50 && @fsockopen('127.0.0.1', $puerto) === false; $i++) {
    usleep(100000);
}
$http = new TransporteHttpStream();
$cliente = new ClienteNotasSoap($url, $http, new CircuitoInterruptor(1000), 2.0, 0);

// C) servicio separado
[$ms, $err] = $cronometrar($nRed, static fn (): bool => $cliente->registrar('token-mendez', 'hosp-oriente', $nota)->estadoHttp === 201);
$filas[] = $resumen('C) Servicio separado por red local (php -S + cliente)', $ms, $err);

// D) servicio separado que además consulta 2 servicios vecinos antes de registrar
[$ms, $err] = $cronometrar($nRed, static function () use ($cliente, $nota): bool {
    $ok = $cliente->notasDeExpediente('token-mendez', 'hosp-oriente', 'exp-carlos')->estadoHttp === 200;   // "Expediente"
    $ok = $cliente->notasDeExpediente('token-mendez', 'hosp-oriente', 'exp-carlos')->estadoHttp === 200 && $ok; // "Asignaciones"

    return $cliente->registrar('token-mendez', 'hosp-oriente', $nota)->estadoHttp === 201 && $ok;
});
$filas[] = $resumen('D) C + 2 consultas remotas previas (vecinos también separados)', $ms, $err);

// C0) control de red
[$ms, $err] = $cronometrar($nRed, static fn (): bool => $http->enviar('GET', "$url/no-existe", [], null, 2.0)->estado === 404);
$filas[] = $resumen('C0) Control: petición mínima (404) por la misma red', $ms, $err);

proc_terminate($proc);

printf("Micro-HIS Notas SOAP - medición de la frontera (PHP %s, %s, adaptadores en memoria)\n", PHP_VERSION, PHP_OS_FAMILY);
printf("Comando: php bin/medir.php %d %d\n\n", $nProceso, $nRed);
printf("%-62s %6s %10s %10s %10s %8s %10s\n", 'Escenario', 'n', 'p50 ms', 'p95 ms', 'p99 ms', 'errores', 'x p50 A');
$base = $filas[0][2];
foreach ($filas as [$nombre, $n, $p50, $p95, $p99, $e]) {
    printf("%-62s %6d %10.3f %10.3f %10.3f %8d %10s\n", $nombre, $n, $p50, $p95, $p99, $e, number_format($p50 / $base, 1, ',', '.') . 'x');
}

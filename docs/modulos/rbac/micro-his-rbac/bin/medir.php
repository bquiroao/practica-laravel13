<?php

declare(strict_types=1);

/*
 * Mide el costo de una decisión de autorización en tres despliegues posibles:
 *   A) módulo dentro del mismo proceso (llamada a AutorizarOperacion)
 *   B) API HTTP en proceso (Kernel completo, sin red)
 *   C) servicio separado por red local (php -S + ClienteRbac sobre HTTP real)
 * Sirve para justificar con datos si conviene separar RBAC como microservicio.
 *
 *   php bin/medir.php [iteraciones_en_proceso] [iteraciones_red]
 *
 * Los adaptadores son InMemory: se mide la arquitectura, no PostgreSQL.
 */

use Rbac\Aplicacion\Comandos\ComandoAutorizar;
use Rbac\Cliente\CircuitoInterruptor;
use Rbac\Cliente\ClienteRbac;
use Rbac\Cliente\TransporteHttpStream;
use Rbac\Persistencia\EnMemoria\FabricaEnMemoria;
use Rbac\Persistencia\EnMemoria\RegistroAuditoriaEnMemoria;
use Rbac\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Rbac\Presentacion\Http\Solicitud;

require dirname(__DIR__) . '/bootstrap/contenedor.php';

$nProceso = (int) ($argv[1] ?? 5000);
$nRed = (int) ($argv[2] ?? 300);
$raiz = dirname(__DIR__);
$config = require $raiz . '/config/config.php';

/** @param list<float> $muestras ms */
function resumen(string $nombre, array $muestras): array
{
    sort($muestras);
    $p = static fn (float $q): float => $muestras[(int) min(count($muestras) - 1, floor($q * count($muestras)))];

    return [
        'escenario' => $nombre,
        'n' => count($muestras),
        'p50_ms' => round($p(0.50), 3),
        'p95_ms' => round($p(0.95), 3),
        'p99_ms' => round($p(0.99), 3),
        'media_ms' => round(array_sum($muestras) / count($muestras), 3),
    ];
}

function medir(int $n, callable $operacion): array
{
    for ($i = 0; $i < min(50, $n); $i++) {
        $operacion($i); // calentamiento, no se cuenta
    }
    $muestras = [];
    for ($i = 0; $i < $n; $i++) {
        $t = hrtime(true);
        $operacion($i);
        $muestras[] = (hrtime(true) - $t) / 1e6;
    }

    return $muestras;
}

$casos = construirCasosDeUso(
    FabricaEnMemoria::crear(require $raiz . '/database/datos-demo.php'),
    new RegistroAuditoriaEnMemoria(),
    generarUuid(...),
    static fn (): DateTimeImmutable => new DateTimeImmutable(),
);
$kernel = construirKernel($casos, new AutenticadorTokenDemo($config['tokens_demo']));
// Mezcla realista: la mitad permitidas (médica), la mitad denegadas y auditadas (enfermera).
$usuario = static fn (int $i): array => $i % 2 === 0 ? ['marta', 'token-marta'] : ['ana', 'token-ana'];

$resultados = [];

$resultados[] = resumen('A) módulo en proceso', medir($nProceso, static function (int $i) use ($casos, $usuario): void {
    $casos['autorizar']->ejecutar(new ComandoAutorizar($usuario($i)[0], 'hosp-oriente', 'hosp-oriente', 'prescripciones.crear', 'bench', '127.0.0.1'));
}));

$resultados[] = resumen('B) API HTTP en proceso', medir($nProceso, static function (int $i) use ($kernel, $usuario): void {
    $kernel->manejar(new Solicitud('POST', '/api/v1/autorizaciones/decisiones', [
        'Authorization' => 'Bearer ' . $usuario($i)[1], 'X-Tenant-ID' => 'hosp-oriente',
    ], ['permiso' => 'prescripciones.crear', 'recurso' => 'bench']));
}));

// C) Servicio separado: levanta el servidor embebido en modo memoria y lo consulta por HTTP real.
$puerto = 8097;
$log = tempnam(sys_get_temp_dir(), 'rbac-srv');
$servidor = proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:$puerto", '-t', $raiz . '/public'],
    [0 => ['pipe', 'r'], 1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']],
    $tuberias,
    $raiz,
    ['MICROHIS_PERSISTENCIA' => 'memoria', 'MICROHIS_RESPALDO' => sys_get_temp_dir() . '/rbac-respaldo.jsonl'] + getenv(),
);
try {
    $cliente = new ClienteRbac("http://127.0.0.1:$puerto", new TransporteHttpStream(), new CircuitoInterruptor(), timeoutSegundos: 2.0, reintentos: 0);
    $listo = false;
    for ($i = 0; $i < 50 && !$listo; $i++) {
        usleep(100_000);
        $listo = @fsockopen('127.0.0.1', $puerto) !== false;
    }
    if (!$listo) {
        throw new RuntimeException('El servidor embebido no arrancó');
    }

    $fallas = 0;
    $muestrasRed = medir($nRed, static function (int $i) use ($cliente, $usuario, &$fallas): void {
        $d = $cliente->verificar($usuario($i)[1], 'hosp-oriente', 'prescripciones.crear', 'bench');
        if ($d->origen !== 'SERVICIO' || $d->permitido !== ($i % 2 === 0)) {
            $fallas++;
        }
    });
    $resultados[] = resumen('C) servicio por red local', $muestrasRed) + ['decisiones_incorrectas' => $fallas];

    // Control: petición mínima (ruta inexistente → 404) por la misma red, sin lógica de RBAC.
    // Aísla cuánto del costo de C es el salto de red + arranque por petición.
    $transporte = new TransporteHttpStream();
    $resultados[] = resumen('C0) control: 404 por red', medir($nRed, static function () use ($transporte, $puerto): void {
        $transporte->enviar('GET', "http://127.0.0.1:$puerto/ping", [], null, 2.0);
    }));
} finally {
    proc_terminate($servidor);
    proc_close($servidor);
    @unlink($log);
}

$base = $resultados[0]['p50_ms'];
echo 'Micro-HIS RBAC — costo de una decisión de autorización (PHP ' . PHP_VERSION . ', ' . PHP_OS_FAMILY . ')' . PHP_EOL;
echo str_repeat('-', 92) . PHP_EOL;
printf("%-28s %6s %10s %10s %10s %10s %12s\n", 'Escenario', 'n', 'p50 ms', 'p95 ms', 'p99 ms', 'media ms', 'x vs. A p50');
foreach ($resultados as $r) {
    printf("%-28s %6d %10.3f %10.3f %10.3f %10.3f %11.0fx\n", $r['escenario'], $r['n'], $r['p50_ms'], $r['p95_ms'], $r['p99_ms'], $r['media_ms'], $r['p50_ms'] / max($base, 0.0001));
}
echo str_repeat('-', 92) . PHP_EOL;
echo 'Decisiones incorrectas por red: ' . ($resultados[2]['decisiones_incorrectas'] ?? 'n/d') . PHP_EOL;
echo 'Nota: C usa el servidor embebido de PHP (un proceso, arranque por petición); es indicativo, no de producción.' . PHP_EOL;

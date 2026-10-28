<?php

declare(strict_types=1);

/*
 * Semana 5 — ¿Cuánto cuesta la frontera? Registra N movimientos y mide la latencia por movimiento:
 *   A) en proceso, adaptadores en memoria          (el módulo dentro del monolito, sin base)
 *   B) en proceso, PostgreSQL                       (el módulo dentro del monolito, con base)
 *   C) por HTTP (php -S) + PostgreSQL, con el ClienteAuditoria (el módulo como servicio aparte)
 *   D) consulta filtrada por HTTP sobre lo registrado
 * Uso: AUDITORIA_DB_DSN=… php bin/medir.php [N=200] [URL=http://127.0.0.1:8088]
 */

require dirname(__DIR__) . '/bootstrap/contenedor.php';

use Auditoria\Aplicacion\Comandos\ComandoRegistrar;
use Auditoria\Cliente\BandejaEnMemoria;
use Auditoria\Cliente\CircuitoInterruptor;
use Auditoria\Cliente\ClienteAuditoria;
use Auditoria\Cliente\TransporteHttpStream;
use Auditoria\Persistencia\Configuracion\PoliticaAccesoEstatica;
use Auditoria\Persistencia\Configuracion\RelojSistema;
use Auditoria\Persistencia\FabricaAdaptadores;
use Auditoria\Persistencia\Pdo\FabricaConexion;

$n = (int) ($argv[1] ?? 200);
$url = $argv[2] ?? 'http://127.0.0.1:8088';
$dsn = getenv('AUDITORIA_DB_DSN') ?: exit("Defina AUDITORIA_DB_DSN.\n");
$config = require dirname(__DIR__) . '/config/config.php';
$corrida = bin2hex(random_bytes(4));

$movimiento = static fn (string $escenario, int $i): array => [
    'event_id' => "medir-$corrida-$escenario-$i", 'hospital' => 'hosp-oriente', 'usuario' => 'u-medicion', 'modulo' => 'pacientes',
    'accion' => 'PACIENTE_CONSULTADO', 'ocurrido_en' => (new DateTimeImmutable('-1 minute'))->format(DATE_ATOM), 'resultado' => 'exito',
    'recurso' => "paciente:P-$i", 'detalle' => ['corrida' => $corrida],
];

/** @param list<float> $ms */
$resumen = static function (string $nombre, array $ms): array {
    sort($ms);
    $p = static fn (float $q): float => $ms[(int) floor($q * (count($ms) - 1))];
    printf("%-58s n=%-4d p50=%7.3f ms   p95=%7.3f ms   máx=%7.3f ms\n", $nombre, count($ms), $p(0.5), $p(0.95), max($ms));

    return ['p50' => $p(0.5), 'p95' => $p(0.95)];
};

$medir = static function (callable $operacion) use ($n): array {
    $ms = [];
    for ($i = 1; $i <= $n; $i++) {
        $t = hrtime(true);
        $operacion($i);
        $ms[] = (hrtime(true) - $t) / 1e6;
    }

    return $ms;
};

echo "Micro-HIS Auditoría · costo de la frontera (PHP " . PHP_VERSION . ", $n movimientos por escenario)" . PHP_EOL . str_repeat('-', 110) . PHP_EOL;
$acceso = new PoliticaAccesoEstatica($config['permisos']);

$memoria = FabricaAdaptadores::enMemoria();
$casosA = construirCasosDeUso($config, $memoria->bitacora, $memoria->transaccion, $acceso, new RelojSistema());
$a = $resumen('A · en proceso, memoria', $medir(static fn (int $i) => $casosA['registrar']->ejecutar(
    new ComandoRegistrar('svc-pacientes', 'hosp-oriente', $movimiento('A', $i)))));

$pdo = FabricaConexion::crear($dsn, getenv('AUDITORIA_DB_USUARIO') ?: 'postgres', getenv('AUDITORIA_DB_CLAVE') ?: '');
$base = FabricaAdaptadores::pdo($pdo);
$casosB = construirCasosDeUso($config, $base->bitacora, $base->transaccion, $acceso, new RelojSistema());
$b = $resumen('B · en proceso, PostgreSQL', $medir(static fn (int $i) => $casosB['registrar']->ejecutar(
    new ComandoRegistrar('svc-pacientes', 'hosp-oriente', $movimiento('B', $i)))));

$cliente = new ClienteAuditoria(new TransporteHttpStream($url), new CircuitoInterruptor(), new BandejaEnMemoria(), 'token-svc-pacientes-oriente',
    'hosp-oriente', timeoutSegundos: 5.0);
$fallas = 0;
$c = $resumen('C · por HTTP (php -S) + PostgreSQL, con ClienteAuditoria', $medir(static function (int $i) use ($cliente, $movimiento, &$fallas): void {
    $fallas += $cliente->registrar($movimiento('C', $i))->registrado() ? 0 : 1;
}));

$auditor = new ClienteAuditoria(new TransporteHttpStream($url), new CircuitoInterruptor(), new BandejaEnMemoria(), 'token-auditor-oriente', 'hosp-oriente',
    timeoutSegundos: 5.0);
$hoy = (new DateTimeImmutable('now', new DateTimeZone('America/Guatemala')))->format('Y-m-d');
$d = $resumen('D · consulta filtrada por HTTP (rango de hoy + usuario + módulo)', $medir(static fn (int $i) => $auditor->consultar(
    ['desde' => $hoy, 'hasta' => $hoy, 'usuario' => 'u-medicion', 'modulo' => 'pacientes'])));

echo str_repeat('-', 110) . PHP_EOL;
printf("Costo de la base (B − A, p50):       %7.3f ms por movimiento\n", $b['p50'] - $a['p50']);
printf("Costo de la red  (C − B, p50):       %7.3f ms por movimiento\n", $c['p50'] - $b['p50']);
printf("Registros por HTTP que NO quedaron:  %d de %d\n", $fallas, $n);
printf("RNF-06 (consulta p95 < 500 ms):      %s (p95 = %.3f ms; la tabla tiene pocas filas: no es prueba de carga)\n", $d['p95'] < 500 ? 'cumple' : 'NO cumple', $d['p95']);

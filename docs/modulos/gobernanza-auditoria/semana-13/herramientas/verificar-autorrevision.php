<?php

declare(strict_types=1);

/**
 * Semana 13 · Reproduce los hallazgos de la autorrevisión del autor (AR-xx) contra el kernel REAL del Micro-HIS
 * Auditoría (adaptadores InMemory de producción). Cada bloque imprime la evidencia que cita el hallazgo.
 *
 * Uso: php herramientas/verificar-autorrevision.php 2>/dev/null   (desde docs/modulos/gobernanza-auditoria/semana-13)
 */
$micro = dirname(__DIR__, 2) . '/micro-his-auditoria';
require $micro . '/bootstrap/contenedor.php';
foreach (glob($micro . '/tests/Dobles/*.php') as $archivo) {
    require_once $archivo;
}

use Auditoria\Aplicacion\Servicios\CursorPaginacion;
use Auditoria\Dominio\Modelo\Movimiento;
use Auditoria\Persistencia\FabricaAdaptadores;
use Auditoria\Presentacion\Http\Solicitud;

$config = require "$micro/config/config.php";
$kernel = crearKernel($config, FabricaAdaptadores::enMemoria());
$hoy = (new DateTimeImmutable('now', new DateTimeZone('America/Guatemala')))->format('Y-m-d');
$pedir = static fn (string $metodo, string $ruta, string $token, string $tenant, ?array $cuerpo = null, array $consulta = []) =>
    $kernel->manejar(new Solicitud($metodo, "/api/v1/auditoria$ruta", ['Authorization' => "Bearer $token", 'X-Tenant-ID' => $tenant], $consulta, $cuerpo));
$json = static fn (array $c): string => mb_strimwidth(json_encode($c, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 160, '…');
$mov = static fn (array $cambios = []): array => array_merge(['event_id' => 'e-' . bin2hex(random_bytes(4)), 'hospital' => 'hosp-oriente', 'usuario' => 'u-ana',
    'modulo' => 'pacientes', 'accion' => 'PACIENTE_CONSULTADO', 'ocurrido_en' => "{$hoy}T00:01:00-06:00", 'resultado' => 'exito', 'recurso' => 'paciente:P-000001'], $cambios);

echo "AR-01 · El punto de entrada real (crearKernel) acepta siempre los tokens de demostración" . PHP_EOL;
$r = $pedir('GET', '/movimientos', 'token-superadmin', '*', null, ['desde' => $hoy, 'hasta' => $hoy]);
$contenedor = (string) file_get_contents("$micro/bootstrap/contenedor.php");
printf("  «Bearer token-superadmin» (un texto fijo del config) + X-Tenant-ID: * → HTTP %d: lectura de TODA la red\n", $r->estado);
printf("  tokens de demostración en config.php: %d · crearKernel() elige el autenticador según el entorno: %s\n\n",
    count($config['tokens_demo']), preg_match('/getenv\(|AUDITORIA_ENTORNO/', $contenedor) ? 'sí' : 'NO (siempre AutenticadorTokenDemo)');

echo "AR-02 · La verificación carga la cadena COMPLETA en memoria (PdoBitacora::cadena → fetchAll)" . PHP_EOL;
$antes = memory_get_usage();
$lista = [];
for ($i = 1; $i <= 20000; $i++) {
    $lista[] = Movimiento::reconstituir($i, 'svc-pacientes', "ev-$i", 'hosp-oriente', 'u-ana', 'pacientes', 'PACIENTE_CONSULTADO',
        new DateTimeImmutable('2026-10-21T15:00:00Z'), 'exito', 'paciente:P-000001', 'corr-0001', ['codigo' => 'P-000001'], $i, str_repeat('a', 64));
}
$porFila = (memory_get_usage() - $antes) / 20000;
unset($lista);
printf("  memoria medida por movimiento hidratado: %.0f bytes (20 000 objetos)\n", $porFila);
foreach ([100000, 1000000, 5000000] as $n) {
    printf("  cadena de %9s movimientos → ≈ %6.0f MB solo en objetos (memory_limit por defecto de PHP: 128 MB)\n", number_format($n, 0, ',', '.'), $n * $porFila / 1048576);
}
printf("  SQL de cadena(): %s\n\n", preg_match('/function cadena.*?fetchAll\(\)/s', (string) file_get_contents("$micro/src/Persistencia/Pdo/PdoBitacora.php")) ? 'SELECT … ORDER BY secuencia + fetchAll() (sin lotes)' : 'por lotes');

echo "AR-03 · DEF-07-01 sigue abierto: el cursor acepta una fecha imposible" . PHP_EOL;
$p = CursorPaginacion::leer(rtrim(strtr(base64_encode('2026-13-45T99:00:00Z|1'), '+/', '-_'), '='));
printf("  leer(«2026-13-45T99:00:00Z|1») → %s (debería ser null → 422)\n\n", $p === null ? 'null' : $p[0]->format(DATE_ATOM));

echo "AR-04 · El recurso es texto libre: el mismo paciente con otro formato no aparece en «¿quién vio la ficha?»" . PHP_EOL;
foreach (['paciente:P-000001', 'P-000001', 'Paciente:p-000001'] as $recurso) {
    $r = $pedir('POST', '/movimientos', 'token-svc-pacientes-oriente', 'hosp-oriente', $mov(['recurso' => $recurso]));
    printf("  registrar recurso «%s» → HTTP %d\n", $recurso, $r->estado);
}
$r = $pedir('GET', '/movimientos', 'token-auditor-oriente', 'hosp-oriente', null, ['desde' => $hoy, 'hasta' => $hoy, 'recurso' => 'paciente:P-000001']);
printf("  buscar recurso=paciente:P-000001 → %d de 3 accesos al mismo paciente\n\n", count($r->cuerpo['movimientos']));

echo "AR-05 · La bitácora se observa a sí misma: cada consulta aparece en la siguiente y la cadena crece con las lecturas" . PHP_EOL;
$primera = $pedir('GET', '/movimientos', 'token-auditor-oriente', 'hosp-oriente', null, ['desde' => $hoy, 'hasta' => $hoy]);
$segunda = $pedir('GET', '/movimientos', 'token-auditor-oriente', 'hosp-oriente', null, ['desde' => $hoy, 'hasta' => $hoy]);
printf("  1.ª consulta: %d filas · 2.ª: %d filas; la primera fila de la 2.ª es «%s»\n", count($primera->cuerpo['movimientos']), count($segunda->cuerpo['movimientos']),
    $segunda->cuerpo['movimientos'][0]['accion']);
$v = $pedir('POST', '/verificaciones', 'token-superadmin', '*', ['hospital' => 'hosp-oriente']);
printf("  la cadena de Oriente tiene %d movimientos: 3 de los emisores y el resto, lecturas\n\n", $v->cuerpo['verificados']);

echo "AR-06 · El event_id es único por emisor en TODA la red, no por hospital" . PHP_EOL;
$r1 = $pedir('POST', '/movimientos', 'token-svc-pacientes-oriente', 'hosp-oriente', $mov(['event_id' => 'evt-0001']));
$r2 = $pedir('POST', '/movimientos', 'token-svc-pacientes-norte', 'hosp-norte', $mov(['event_id' => 'evt-0001', 'hospital' => 'hosp-norte']));
printf("  svc-pacientes en Oriente registra evt-0001 → HTTP %d\n  svc-pacientes en Norte registra OTRO hecho con evt-0001 → HTTP %d %s\n",
    $r1->estado, $r2->estado, $json($r2->cuerpo));
$doc = (string) file_get_contents("$micro/api/openapi.yaml");
printf("  ¿el contrato dice que event_id debe ser único en toda la red? %s\n", preg_match('/event_id[^\n]*(red|global|todos los hospitales)/i', $doc) ? 'sí' : 'NO');

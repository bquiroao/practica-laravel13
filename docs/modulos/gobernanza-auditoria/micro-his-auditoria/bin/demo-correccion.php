<?php

declare(strict_types=1);

/*
 * Semana 6 — Demo del cambio práctico «corrección trazable de un movimiento», por la API real y sin
 * base de datos (AUDITORIA_PERSISTENCIA=memoria).
 *   php bin/demo-correccion.php
 */

putenv('AUDITORIA_PERSISTENCIA=memoria');
require dirname(__DIR__) . '/bootstrap/contenedor.php';

use Auditoria\Presentacion\Http\Solicitud;

$kernel = crearKernel(require dirname(__DIR__) . '/config/config.php');
$base = '/api/v1/auditoria';
$hoy = (new DateTimeImmutable('now', new DateTimeZone('America/Guatemala')))->format('Y-m-d');
$pedir = static function (string $titulo, string $metodo, string $ruta, string $token, string $hospital, ?array $cuerpo = null, array $consulta = [])
    use ($kernel): array {
    $r = $kernel->manejar(new Solicitud($metodo, $ruta, ['Authorization' => "Bearer $token", 'X-Tenant-ID' => $hospital], $consulta, $cuerpo));
    printf("%-60s → %d %s\n", $titulo, $r->estado, mb_strimwidth(json_encode($r->cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 400, '…'));

    return $r->cuerpo;
};
$movimiento = static fn (array $cambios = []): array => array_merge(['event_id' => 'c-1', 'hospital' => 'hosp-oriente', 'usuario' => 'u-equivocado',
    'modulo' => 'pacientes', 'accion' => 'PACIENTE_CONSULTADO', 'ocurrido_en' => "{$hoy}T08:00:00-06:00", 'resultado' => 'exito',
    'recurso' => 'paciente:P-000001'], $cambios);
$correccion = ['event_id' => 'c-2', 'usuario' => 'u-correcto', 'ocurrido_en' => "{$hoy}T09:00:00-06:00", 'corrige_evento' => 'c-1',
    'motivo_correccion' => 'El integrador atribuyó la consulta al usuario equivocado'];

echo "Cambio práctico · corrección trazable (sin base de datos)" . PHP_EOL . str_repeat('-', 120) . PHP_EOL;
$pedir('1. Pacientes registra una consulta con el usuario EQUIVOCADO', 'POST', "$base/movimientos", 'token-svc-pacientes-oriente', 'hosp-oriente', $movimiento());
$pedir('2. Corrige sin motivo', 'POST', "$base/movimientos", 'token-svc-pacientes-oriente', 'hosp-oriente',
    $movimiento(array_diff_key($correccion, ['motivo_correccion' => 1])));
$pedir('3. Corrige con motivo (el original NO se toca)', 'POST', "$base/movimientos", 'token-svc-pacientes-oriente', 'hosp-oriente', $movimiento($correccion));
$pedir('4. Reintento de la misma corrección (idempotente)', 'POST', "$base/movimientos", 'token-svc-pacientes-oriente', 'hosp-oriente', $movimiento($correccion));
$pedir('5. Otra corrección del mismo original', 'POST', "$base/movimientos", 'token-svc-pacientes-oriente', 'hosp-oriente',
    $movimiento(['event_id' => 'c-3'] + $correccion));
$pedir('6. Norte intenta corregir el evento de oriente', 'POST', "$base/movimientos", 'token-svc-pacientes-norte', 'hosp-norte',
    $movimiento(['event_id' => 'n-1', 'hospital' => 'hosp-norte'] + $correccion));
$pedir('7. El auditor consulta el día: el original avisa corregido_por', 'GET', "$base/movimientos", 'token-auditor-oriente', 'hosp-oriente', null,
    ['desde' => $hoy, 'hasta' => $hoy, 'modulo' => 'pacientes']);
$pedir('8. La cadena sigue íntegra', 'POST', "$base/verificaciones", 'token-superadmin', '*', ['hospital' => 'hosp-oriente']);

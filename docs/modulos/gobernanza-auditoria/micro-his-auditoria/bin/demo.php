<?php

declare(strict_types=1);

/*
 * Demo de punta a punta SIN base de datos: el mismo crearKernel() de public/index.php con
 * AUDITORIA_PERSISTENCIA=memoria (adaptadores InMemory de producción).
 *   php bin/demo.php
 */

putenv('AUDITORIA_PERSISTENCIA=memoria');
require dirname(__DIR__) . '/bootstrap/contenedor.php';

use Auditoria\Presentacion\Http\Solicitud;

$kernel = crearKernel(require dirname(__DIR__) . '/config/config.php');
$base = '/api/v1/auditoria';
$pedir = static function (string $titulo, string $metodo, string $ruta, string $token, string $hospital, ?array $cuerpo = null, array $consulta = [])
    use ($kernel): array {
    $r = $kernel->manejar(new Solicitud($metodo, $ruta, ['Authorization' => "Bearer $token", 'X-Tenant-ID' => $hospital], $consulta, $cuerpo));
    printf("%-62s %s %s → %d %s\n", $titulo, $metodo, $ruta . ($consulta === [] ? '' : '?' . http_build_query($consulta)), $r->estado,
        mb_strimwidth(json_encode($r->cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 110, '…'));

    return $r->cuerpo;
};
$movimiento = static fn (string $evento, string $usuario, string $hospital, string $hora): array => [
    'event_id' => $evento, 'hospital' => $hospital, 'usuario' => $usuario, 'modulo' => 'pacientes', 'accion' => 'PACIENTE_CONSULTADO',
    'ocurrido_en' => (new DateTimeImmutable('today 00:00', new DateTimeZone('America/Guatemala')))->format('Y-m-d') . "T$hora:00-06:00",
    'resultado' => 'exito', 'recurso' => 'paciente:P-000001',
];
$hoy = (new DateTimeImmutable('now', new DateTimeZone('America/Guatemala')))->format('Y-m-d');

echo "Micro-HIS Auditoría · demo sin base de datos (AUDITORIA_PERSISTENCIA=memoria)" . PHP_EOL . str_repeat('-', 100) . PHP_EOL;
$pedir('1. Pacientes registra una consulta de ficha', 'POST', "$base/movimientos", 'token-svc-pacientes-oriente', 'hosp-oriente',
    $movimiento('d-1', 'u-ana', 'hosp-oriente', '08:00'));
$pedir('2. Reintento por corte de red (idempotente)', 'POST', "$base/movimientos", 'token-svc-pacientes-oriente', 'hosp-oriente',
    $movimiento('d-1', 'u-ana', 'hosp-oriente', '08:00'));
$pedir('3. Otro usuario en el mismo hospital', 'POST', "$base/movimientos", 'token-svc-pacientes-oriente', 'hosp-oriente',
    $movimiento('d-2', 'u-luis', 'hosp-oriente', '09:30'));
$pedir('4. Un movimiento en hosp-norte', 'POST', "$base/movimientos", 'token-svc-pacientes-norte', 'hosp-norte',
    $movimiento('d-3', 'u-ana', 'hosp-norte', '10:00'));
$pedir('5. El auditor de oriente filtra por usuario', 'GET', "$base/movimientos", 'token-auditor-oriente', 'hosp-oriente', null,
    ['desde' => $hoy, 'hasta' => $hoy, 'usuario' => 'u-ana']);
$pedir('6. El auditor intenta ver la red', 'GET', "$base/movimientos", 'token-auditor-oriente', '*', null, ['desde' => $hoy, 'hasta' => $hoy]);
$pedir('7. El super administrador ve a u-ana en toda la red', 'GET', "$base/movimientos", 'token-superadmin', '*', null,
    ['desde' => $hoy, 'hasta' => $hoy, 'usuario' => 'u-ana']);
$pedir('8. Verificación de la cadena de oriente', 'POST', "$base/verificaciones", 'token-superadmin', '*', ['hospital' => 'hosp-oriente']);

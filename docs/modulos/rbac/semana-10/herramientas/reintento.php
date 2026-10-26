<?php

declare(strict_types=1);

/**
 * Semana 10 · ¿Es seguro reintentar después de un corte de red?
 * Envía dos veces la MISMA solicitud al kernel del Micro-HIS (adaptadores en memoria),
 * como lo haría un teléfono que no recibió la primera respuesta.
 *
 * Uso: php herramientas/reintento.php   (desde docs/modulos/rbac/semana-10)
 */
$micro = dirname(__DIR__, 2) . '/micro-his-rbac';
require $micro . '/bootstrap/contenedor.php';
foreach (glob($micro . '/tests/Dobles/*.php') as $archivo) {
    require_once $archivo;
}

use Rbac\Pruebas\Dobles\Escenario;

$enviarDosVeces = static function (string $ruta, array $cuerpo): array {
    $e = new Escenario();
    $r1 = $e->kernel->manejar(Escenario::http('POST', $ruta, 'token-admin-oriente', Escenario::H_ORIENTE, $cuerpo));
    $r2 = $e->kernel->manejar(Escenario::http('POST', $ruta, 'token-admin-oriente', Escenario::H_ORIENTE, $cuerpo));
    return [$r1->estado, $r2->estado, $r2->cuerpo['codigo'] ?? ''];
};

$casos = [
    'Asignar sin fecha de fin' => ['/api/v1/roles/asignaciones', ['usuario_id' => 'nuevo-01', 'rol' => 'laboratorio', 'valido_desde' => '2026-11-15']],
    'Asignar con fecha de fin' => ['/api/v1/roles/asignaciones', ['usuario_id' => 'nuevo-01', 'rol' => 'laboratorio', 'valido_desde' => '2026-11-15', 'valido_hasta' => '2026-12-31']],
    'Revocar' => ['/api/v1/roles/revocaciones', ['usuario_id' => 'ana', 'rol' => 'enfermeria', 'motivo' => 'Traslado a consulta externa']],
];

foreach ($casos as $nombre => [$ruta, $cuerpo]) {
    [$primero, $segundo, $codigo] = $enviarDosVeces($ruta, $cuerpo);
    $seguro = $segundo !== $primero ? 'SEGURO (no repite el efecto)' : 'NO SEGURO (repite el efecto)';
    printf("%-26s 1.er envío: %d   reintento idéntico: %d %-24s → %s\n", $nombre, $primero, $segundo, $codigo, $seguro);
}

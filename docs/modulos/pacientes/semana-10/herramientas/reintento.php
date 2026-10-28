<?php

declare(strict_types=1);

/**
 * Semana 10 · ¿Es seguro reintentar después de un corte de red?
 * Envía dos veces la MISMA solicitud al kernel del Micro-HIS (adaptadores en memoria),
 * como lo haría un teléfono que no recibió la primera respuesta, y cuenta los pacientes creados.
 *
 * Uso: php herramientas/reintento.php   (desde docs/modulos/pacientes/semana-10)
 */
$micro = dirname(__DIR__, 2) . '/micro-his-pacientes';
require $micro . '/bootstrap/contenedor.php';
foreach (glob($micro . '/tests/Dobles/*.php') as $archivo) {
    require_once $archivo;
}

use Pacientes\Pruebas\Dobles\Escenario;

const TOKEN = 'token-admision-oriente';

$http = static fn (object $e, string $metodo, string $ruta, ?array $cuerpo = null, array $cabeceras = [], array $consulta = []) =>
    $e->kernel->manejar(Escenario::http($metodo, $ruta, TOKEN, $cuerpo, $consulta, $cabeceras));

/** Pacientes con el documento de Sofía (para contar duplicados). */
$cuantos = static fn (object $e): int => $http($e, 'GET', '/api/v1/pacientes', consulta: ['nombre' => 'sofia morales'])->cuerpo['total'] ?? -1;

$sofia = ['nombres' => 'Sofía', 'apellidos' => 'Morales Díaz', 'fecha_nacimiento' => '1994-08-09', 'sexo' => 'female',
    'documento' => ['tipo' => 'DPI', 'numero' => '3011223340101']];
$provisional = ['nombres' => 'Desconocido', 'apellidos' => 'Emergencia 0412', 'fecha_nacimiento' => '1980-01-01', 'sexo' => 'unknown'];

$casos = [
    'Registrar con Idempotency-Key' => static function () use ($http, $cuantos, $sofia): array {
        $e = Escenario::crear();
        $clave = ['Idempotency-Key' => 'movil-7f3a9c21'];
        $r1 = $http($e, 'POST', '/api/v1/pacientes', $sofia, $clave);
        $r2 = $http($e, 'POST', '/api/v1/pacientes', $sofia, $clave);
        $extra = ($r2->cabeceras['Idempotent-Replayed'] ?? '') === 'true' ? 'misma respuesta (Idempotent-Replayed)' : '';
        return [$r1->estado, $r2->estado, $extra, $cuantos($e)];
    },
    'Registrar sin Idempotency-Key' => static function () use ($http, $cuantos, $sofia): array {
        $e = Escenario::crear();
        $r1 = $http($e, 'POST', '/api/v1/pacientes', $sofia);
        $r2 = $http($e, 'POST', '/api/v1/pacientes', $sofia);
        return [$r1->estado, $r2->estado, $r2->cuerpo['error'] ?? '', $cuantos($e)];
    },
    'Registrar provisional sin clave' => static function () use ($http, $provisional): array {
        $e = Escenario::crear();
        $r1 = $http($e, 'POST', '/api/v1/pacientes', $provisional);
        $r2 = $http($e, 'POST', '/api/v1/pacientes', $provisional);
        $total = $http($e, 'GET', '/api/v1/pacientes', consulta: ['nombre' => 'emergencia 0412'])->cuerpo['total'] ?? -1;
        return [$r1->estado, $r2->estado, $r2->cuerpo['error'] ?? '', $total];
    },
    'Editar (PUT con If-Match)' => static function () use ($http): array {
        $e = Escenario::crear();
        $r0 = $http($e, 'POST', '/api/v1/pacientes', Escenario::json(Escenario::datosAna()));
        $cuerpo = Escenario::json(Escenario::datosAna(['telefono' => '+502 4444 0001']));
        $r1 = $http($e, 'PUT', '/api/v1/pacientes/' . $r0->cuerpo['codigo'], $cuerpo, ['If-Match' => '"1"']);
        $r2 = $http($e, 'PUT', '/api/v1/pacientes/' . $r0->cuerpo['codigo'], $cuerpo, ['If-Match' => '"1"']);
        return [$r1->estado, $r2->estado, $r2->cuerpo['error'] ?? '', null];
    },
];

printf("%-32s %-9s %-11s %-42s %s\n", 'Operación', '1.er env.', 'Reintento', 'Respuesta del reintento', 'Pacientes');
echo str_repeat('-', 112) . PHP_EOL;
foreach ($casos as $nombre => $caso) {
    [$primero, $segundo, $detalle, $pacientes] = $caso();
    printf("%-32s %-9d %-11d %-42s %s\n", $nombre, $primero, $segundo, $detalle, $pacientes === null ? '—' : (string) $pacientes);
}

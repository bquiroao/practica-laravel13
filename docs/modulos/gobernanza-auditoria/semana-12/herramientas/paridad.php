<?php

declare(strict_types=1);

/**
 * Semana 11 · Ejecuta paridad-secuencia.json contra el kernel REAL del Micro-HIS (adaptadores InMemory de producción,
 * envueltos en el doble de pruebas que permite «borrar directo en la base») e imprime una huella JSON por paso.
 * Recibe por stdin {"semilla": [...], "borrado": {...}, "hoy": "AAAA-MM-DD"}: la MISMA semilla del prototipo (datos.js).
 * paridad.cjs compara estas huellas con las de la API simulada.
 */
$micro = dirname(__DIR__, 2) . '/micro-his-auditoria';
require $micro . '/bootstrap/contenedor.php';
foreach (glob($micro . '/tests/Dobles/*.php') as $archivo) {
    require_once $archivo;
}

use Auditoria\Persistencia\ConjuntoAdaptadores;
use Auditoria\Persistencia\EnMemoria\TransaccionEnMemoria;
use Auditoria\Presentacion\Http\Solicitud;
use Auditoria\Pruebas\Dobles\BitacoraConFallas;

$entrada = json_decode((string) stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$config = require $micro . '/config/config.php';
$bitacora = new BitacoraConFallas();
$kernel = crearKernel($config, new ConjuntoAdaptadores($bitacora, new TransaccionEnMemoria([$bitacora->real])));

$enviar = static function (string $metodo, string $url, string $token, string $tenant, ?array $cuerpo) use ($kernel) {
    [$ruta, $qs] = array_pad(explode('?', $url, 2), 2, '');
    parse_str($qs, $consulta);
    $cabeceras = ['X-Tenant-ID' => $tenant] + ($token === '' ? [] : ['Authorization' => 'Bearer ' . $token]);

    return $kernel->manejar(new Solicitud($metodo, $ruta, $cabeceras, $consulta, $cuerpo));
};

// 1. La semilla, por la API (las 9 reglas se aplican de verdad)
$huellas = ['semilla' => []];
foreach ($entrada['semilla'] as $i => $s) {
    $r = $enviar('POST', '/api/v1/auditoria/movimientos', $s['token'], $s['tenant'], $s['cuerpo']);
    $huellas['semilla'][] = [$r->estado, $r->cuerpo['id'] ?? null, $r->cuerpo['secuencia'] ?? null, $r->cuerpo['error'] ?? null, $r->cuerpo['campos'] ?? null];
}
// 2. «Alguien» borra una fila directo en la base, saltándose el trigger
foreach ($bitacora->real->todos() as $m) {
    if ($m->emisor === $entrada['borrado']['emisor'] && $m->eventId === $entrada['borrado']['event_id']) {
        $bitacora->ocultarParaPrueba((int) $m->id);
    }
}

// 3. La secuencia
$pasos = json_decode((string) file_get_contents(__DIR__ . '/paridad-secuencia.json'), true, flags: JSON_THROW_ON_ERROR);
$manana = (new DateTimeImmutable($entrada['hoy']))->modify('+1 day')->format('Y-m-d');
$cursor = '';
$huellas['pasos'] = [];
foreach ($pasos as $p) {
    $ruta = str_replace(['{HOY}', '{MANANA}', '{CURSOR}'], [$entrada['hoy'], $manana, rawurlencode($cursor)], $p['ruta']);
    $r = $enviar($p['metodo'], $ruta, $p['token'], $p['tenant'] ?? 'hosp-oriente', $p['cuerpo'] ?? null);
    $cursor = $r->cuerpo['cursor_siguiente'] ?? $cursor;
    $huellas['pasos'][] = huella($r->estado, $r->cuerpo);
}
echo json_encode($huellas, JSON_UNESCAPED_UNICODE);

/** El mismo cálculo que huella() en paridad.cjs */
function huella(int $estado, array $c): array
{
    $movs = $c['movimientos'] ?? null;
    $m = $c['movimiento'] ?? null;

    return [
        'estado' => $estado, 'error' => $c['error'] ?? null, 'campos' => isset($c['campos']) ? array_keys($c['campos']) : null,
        'ids' => $movs === null ? null : array_column($movs, 'id'),
        'acciones' => $movs === null ? null : array_values(array_unique(array_column($movs, 'accion'))),
        'corregidos' => $movs === null ? null : array_values(array_filter(array_map(static fn (array $x) => $x['corregido_por'] === null ? null : "{$x['id']}→{$x['corregido_por']}", $movs))),
        'cursor' => array_key_exists('cursor_siguiente', $c) ? $c['cursor_siguiente'] !== null : null,
        'detalle' => $m === null ? null : [$m['id'], $m['usuario'], $m['corrige_a'], $m['motivo_correccion'], $m['corregido_por'], $m['secuencia']],
        'verificacion' => isset($c['estado']) ? [$c['estado'], $c['primera_secuencia'] ?? $c['esperada_hasta'] ?? null, $c['verificados']] : null,
    ];
}

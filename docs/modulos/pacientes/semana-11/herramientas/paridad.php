<?php

declare(strict_types=1);

/**
 * Semana 11 · Ejecuta paridad-secuencia.json contra el kernel REAL del Micro-HIS (adaptadores en memoria)
 * e imprime una «huella» JSON por paso. paridad.cjs compara estas huellas con las de la API simulada.
 * Antes de la secuencia registra la misma semilla que el prototipo (prototipo/datos.js).
 */
$micro = dirname(__DIR__, 2) . '/micro-his-pacientes';
require $micro . '/bootstrap/contenedor.php';
foreach (glob($micro . '/tests/Dobles/*.php') as $archivo) {
    require_once $archivo;
}

use Pacientes\Presentacion\Http\Solicitud;
use Pacientes\Pruebas\Dobles\Escenario;

$e = Escenario::crear();
$hospitalDe = ['token-admision-norte' => 'hosp-norte'];
$enviar = static function (string $metodo, string $url, string $token, ?array $cuerpo, array $cabeceras = [], ?string $tenant = null) use ($e, $hospitalDe) {
    [$ruta, $qs] = array_pad(explode('?', $url, 2), 2, '');
    parse_str($qs, $consulta);
    $base = ['X-Tenant-ID' => $tenant ?? ($hospitalDe[$token] ?? 'hosp-oriente')];
    if ($token !== '') {
        $base['Authorization'] = 'Bearer ' . $token;
    }

    return $e->kernel->manejar(new Solicitud($metodo, $ruta, $cabeceras + $base, $consulta, $cuerpo));
};

// La misma semilla que prototipo/datos.js (P-000001…P-000005): paridad.cjs la envía por la entrada estándar.
$semilla = json_decode((string) stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
foreach ($semilla as [$token, $cuerpo]) {
    $enviar('POST', '/api/v1/pacientes', $token, $cuerpo);
}

$huellas = [];
foreach (json_decode((string) file_get_contents(__DIR__ . '/paridad-secuencia.json'), true, flags: JSON_THROW_ON_ERROR) as $p) {
    $r = $enviar($p['metodo'], $p['ruta'], $p['token'], $p['cuerpo'] ?? null, $p['cabeceras'] ?? [], $p['tenant'] ?? null);
    $huellas[] = huella($r->estado, $r->cuerpo, $r->cabeceras);
}
echo json_encode($huellas, JSON_UNESCAPED_UNICODE);

/** Lo que importa comparar de una respuesta (el mismo cálculo está en paridad.cjs). */
function huella(int $estado, array $c, array $cab): array
{
    $paciente = $c['paciente'] ?? ($c['resultados'][0] ?? null);
    $campos = isset($c['campos']) ? array_keys($c['campos']) : null;
    if ($campos !== null) {
        sort($campos);
    }

    return [
        'estado' => $estado,
        'error' => $c['error'] ?? null,
        'codigo' => $c['codigo'] ?? null,
        'version' => $c['version'] ?? ($c['paciente']['version'] ?? null),
        'total' => $c['total'] ?? null,
        'campos' => $campos,
        'candidatos' => $c['candidatos'] ?? null,
        'identificador' => $c['identificador'] ?? ($paciente['identificador'] ?? null),
        'nombre' => $paciente['nombre'] ?? null,
        'telefono' => $c['paciente']['telefono'] ?? null,
        'repetida' => ($cab['Idempotent-Replayed'] ?? null) === 'true',
    ];
}

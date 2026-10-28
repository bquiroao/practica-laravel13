<?php

declare(strict_types=1);

/**
 * Semana 11 · Ejecuta paridad-secuencia.json contra el kernel REAL del Micro-HIS (adaptadores en memoria)
 * e imprime una «huella» JSON por paso. paridad.cjs compara estas huellas con las de la API simulada.
 */
$micro = dirname(__DIR__, 2) . '/micro-his-rbac';
require $micro . '/bootstrap/contenedor.php';
foreach (glob($micro . '/tests/Dobles/*.php') as $archivo) {
    require_once $archivo;
}

use Rbac\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Rbac\Presentacion\Http\Solicitud;
use Rbac\Pruebas\Dobles\Escenario;

$e = new Escenario();
// Mismo sistema del Escenario, con un token más para nuevo-01 (la persona que recibe el rol en el camino feliz).
$kernel = construirKernel($e->casos(), new AutenticadorTokenDemo([
    'token-admin-oriente' => ['admin-01', Escenario::H_ORIENTE],
    'token-admin-norte' => ['admin-02', Escenario::H_NORTE],
    'token-ana' => ['ana', Escenario::H_ORIENTE],
    'token-marta' => ['marta', Escenario::H_ORIENTE],
    'token-nuevo-01' => ['nuevo-01', Escenario::H_ORIENTE],
]));

$pasos = json_decode((string) file_get_contents(__DIR__ . '/paridad-secuencia.json'), true, flags: JSON_THROW_ON_ERROR);
$guardadas = [];
$huellas = [];
foreach ($pasos as $p) {
    $ruta = preg_replace_callback('/\{(\w+)\}/', static fn (array $m): string => $guardadas[$m[1]] ?? 'sin-ref', $p['ruta']);
    $cabeceras = ['Authorization' => 'Bearer ' . $p['token'], 'X-Tenant-ID' => $p['tenant'] ?? Escenario::H_ORIENTE];
    $r = $kernel->manejar(new Solicitud($p['metodo'], $ruta, $cabeceras, $p['cuerpo'] ?? null, '10.20.5.14'));
    $c = $r->cuerpo;
    if (isset($p['guardar']) && is_string($c['correlation_id'] ?? null)) {
        $guardadas[$p['guardar']] = $c['correlation_id'];
    }
    $huellas[] = [
        'estado' => $r->estado,
        'codigo' => $c['codigo'] ?? null,
        'decision' => $c['decision'] ?? null,
        'campos' => $c['campos'] ?? null,
        'con_referencia' => is_string($c['correlation_id'] ?? null),
        'tipo' => $c['tipo'] ?? null,
        'actor' => $c['actor'] ?? null,
        'motivo' => $c['datos']['motivo'] ?? null,
        'permiso' => $c['datos']['permiso'] ?? null,
    ];
}
echo json_encode($huellas, JSON_UNESCAPED_UNICODE);

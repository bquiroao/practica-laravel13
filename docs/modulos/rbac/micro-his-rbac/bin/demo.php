<?php

declare(strict_types=1);

/*
 * Demostración del flujo completo en un solo proceso, sin base de datos.
 * Usa los MISMOS controladores, casos de uso y reglas que la API, sobre los
 * adaptadores InMemory. Cambiando a PDO, el recorrido sería idéntico.
 *
 *   php bin/demo.php
 */

use Rbac\Persistencia\EnMemoria\FabricaEnMemoria;
use Rbac\Persistencia\EnMemoria\RegistroAuditoriaEnMemoria;
use Rbac\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Rbac\Presentacion\Http\Solicitud;

require dirname(__DIR__) . '/bootstrap/contenedor.php';

$config = require dirname(__DIR__) . '/config/config.php';
$casos = construirCasosDeUso(
    FabricaEnMemoria::crear(require dirname(__DIR__) . '/database/datos-demo.php'),
    new RegistroAuditoriaEnMemoria(),
    generarUuid(...),
    static fn (): DateTimeImmutable => new DateTimeImmutable('2026-11-15T10:00:00-06:00'),
);
$kernel = construirKernel($casos, new AutenticadorTokenDemo($config['tokens_demo']));

$enviar = static function (string $titulo, string $metodo, string $ruta, string $token, ?array $cuerpo = null) use ($kernel): array {
    $respuesta = $kernel->manejar(new Solicitud($metodo, $ruta, [
        'Authorization' => 'Bearer ' . $token,
        'X-Tenant-ID' => 'hosp-oriente',
    ], $cuerpo, '10.20.5.14'));

    echo PHP_EOL . '▶ ' . $titulo . PHP_EOL;
    echo "  $metodo $ruta  (token: $token)" . PHP_EOL;
    echo '  ← ' . $respuesta->estado . ' ' . json_encode($respuesta->cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

    return $respuesta->cuerpo;
};

echo 'Micro-HIS RBAC — demostración (persistencia: memoria)' . PHP_EOL;

$enviar('1. El administrador asigna el rol «laboratorio» a un usuario nuevo', 'POST', '/api/v1/roles/asignaciones', 'token-admin-oriente', [
    'usuario_id' => 'nuevo-01', 'rol' => 'laboratorio', 'valido_desde' => '2026-11-15T00:00:00-06:00',
]);
$enviar('2. La enfermera registra signos vitales (permitido)', 'POST', '/api/v1/signos-vitales', 'token-ana');
$denegada = $enviar('3. La enfermera intenta crear una prescripción (denegado, sin revelar el motivo)', 'POST', '/api/v1/prescripciones', 'token-ana');
$enviar('4. El administrador consulta la denegación por su correlation_id', 'GET', '/api/v1/auditoria/eventos/' . $denegada['correlation_id'], 'token-admin-oriente');
$enviar('5. La enfermera intenta asignarse un rol (denegado: no tiene roles.asignar)', 'POST', '/api/v1/roles/asignaciones', 'token-ana', [
    'usuario_id' => 'ana', 'rol' => 'medico', 'valido_desde' => '2026-11-15T00:00:00-06:00',
]);

// Semana 6 — cambio práctico: revocar un rol cambia la decisión de autorización.
$enviar('6. El administrador revoca el rol «enfermeria» de la enfermera, con motivo', 'POST', '/api/v1/roles/revocaciones', 'token-admin-oriente', [
    'usuario_id' => 'ana', 'rol' => 'enfermeria', 'motivo' => 'Traslado a otro hospital de la red',
]);
$tras = $enviar('7. La enfermera intenta registrar signos vitales otra vez (ahora denegado)', 'POST', '/api/v1/signos-vitales', 'token-ana');
$enviar('8. El administrador ve el motivo de esa denegación en la bitácora', 'GET', '/api/v1/auditoria/eventos/' . $tras['correlation_id'], 'token-admin-oriente');

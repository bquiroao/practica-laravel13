<?php

declare(strict_types=1);

// Observabilidad: id de traza en cada respuesta y registro de acceso estructurado sin secretos.

use Rbac\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Rbac\Presentacion\Http\Solicitud;
use Rbac\Pruebas\Dobles\Escenario;

prueba('OBS-01', 'X-Request-ID: se respeta el recibido si es válido y se genera uno nuevo si falta o es inválido', function (): void {
    $k = (new Escenario())->kernel;
    $con = static fn (?string $id): Solicitud => new Solicitud('POST', '/api/v1/signos-vitales', array_filter([
        'Authorization' => 'Bearer token-ana', 'X-Tenant-ID' => 'hosp-oriente', 'X-Request-ID' => $id,
    ]));
    igual('traza-externa-123', $k->manejar($con('traza-externa-123'))->cabeceras['X-Request-ID'] ?? null, 'recibido válido');
    $generado = $k->manejar($con(null))->cabeceras['X-Request-ID'] ?? '';
    afirmar(preg_match('/^[a-f0-9-]{36}$/', $generado) === 1, 'debió generarse un UUID');
    $inyectado = $k->manejar($con('<script>alert(1)</script>'))->cabeceras['X-Request-ID'] ?? '';
    afirmar($inyectado !== '<script>alert(1)</script>' && preg_match('/^[a-f0-9-]{36}$/', $inyectado) === 1, 'un id inválido no se refleja');
    afirmar(isset($k->manejar(Escenario::http('GET', '/no-existe', null))->cabeceras['X-Request-ID']), 'también en errores (404)');
});

prueba('OBS-02', 'Registro de acceso: un registro por petición con estado, duración, usuario y correlation_id; nunca el token', function (): void {
    $e = new Escenario();
    $e->kernel->manejar(Escenario::http('POST', '/api/v1/prescripciones', 'token-ana'));
    $e->kernel->manejar(Escenario::http('POST', '/api/v1/prescripciones', 'token-falso'));
    igual(2, count($e->accesos), 'registros');

    [$denegado, $sinIdentidad] = $e->accesos;
    igual([403, 'ana', 'hosp-oriente'], [$denegado['estado'], $denegado['usuario'], $denegado['tenant']], 'campos del 403');
    afirmar(is_string($denegado['correlation_id']) && is_float($denegado['duracion_ms']), 'correlation_id y duración');
    igual([401, null], [$sinIdentidad['estado'], $sinIdentidad['usuario']], 'campos del 401');

    $texto = (string) json_encode($e->accesos);
    afirmar(!str_contains($texto, 'token-ana') && !str_contains($texto, 'token-falso') && !str_contains($texto, 'Bearer'), 'el log contiene tokens');
    afirmar(!str_contains($texto, 'PERMISO_NO_ASIGNADO'), 'el log de acceso no debe llevar el motivo (va a la bitácora de auditoría)');
});

prueba('OBS-03', 'Si el registro de acceso falla, la respuesta al usuario no cambia', function (): void {
    $e = new Escenario();
    $kernel = construirKernel($e->casos(), new AutenticadorTokenDemo(['token-ana' => ['ana', 'hosp-oriente']]), static function (array $r): void {
        throw new RuntimeException('disco lleno');
    });
    igual(201, $kernel->manejar(Escenario::http('POST', '/api/v1/signos-vitales', 'token-ana'))->estado, 'HTTP');
});

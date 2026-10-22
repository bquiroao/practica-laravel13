<?php

declare(strict_types=1);

use Pacientes\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Pacientes\Presentacion\Http\Identidad;
use Pacientes\Presentacion\Http\Kernel;
use Pacientes\Presentacion\Http\Respuesta;
use Pacientes\Presentacion\Http\Ruta;
use Pacientes\Presentacion\Http\Solicitud;
use Pacientes\Pruebas\Dobles\Escenario;

$post = static fn (object $e, array $datos, array $extra = [], string $token = 'token-admision-oriente'): Respuesta =>
    $e->kernel->manejar(Escenario::http('POST', '/api/v1/pacientes', $token, Escenario::json($datos, $extra)));

prueba('HTTP-01', 'POST /api/v1/pacientes → 201 con Location, ETag y el código', function () use ($post): void {
    $e = Escenario::crear();
    $r = $post($e, Escenario::datosAna());
    igual(201, $r->estado, 'HTTP');
    igual(['codigo' => 'P-000001', 'version' => 1, 'identidad_provisional' => false], $r->cuerpo, 'cuerpo');
    igual(['/api/v1/pacientes/P-000001', '"1"'], [$r->cabeceras['Location'] ?? null, $r->cabeceras['ETag'] ?? null], 'Location y ETag');
    afirmar(isset($r->cabeceras['X-Request-ID']), 'falta X-Request-ID (semana 5)');
});

prueba('HTTP-02', 'Sin X-Tenant-ID → 400; sin token → 401; token de otro hospital → 403', function (): void {
    $e = Escenario::crear();
    igual(400, $e->kernel->manejar(new Solicitud('GET', '/api/v1/pacientes', ['Authorization' => 'Bearer token-admision-oriente']))->estado, 'sin hospital');
    igual(401, $e->kernel->manejar(Escenario::http('GET', '/api/v1/pacientes', null))->estado, 'sin token');
    $r = $e->kernel->manejar(Escenario::http('GET', '/api/v1/pacientes', 'token-admision-norte'));
    igual([403, 'TENANT_NO_COINCIDE'], [$r->estado, $r->cuerpo['error']], 'token de Norte en Oriente');
});

prueba('HTTP-03', 'Reglas hacia HTTP: cuerpo no JSON 400, datos inválidos 422, DPI repetido 409, clínico 403', function () use ($post): void {
    $e = Escenario::crear();
    igual(400, $e->kernel->manejar(Escenario::http('POST', '/api/v1/pacientes', cuerpo: null))->estado, 'cuerpo');
    $r = $post($e, Escenario::datosAna(['sexo' => 'x', 'fecha_nacimiento' => '2030-01-01']));
    igual([422, 'DATOS_INVALIDOS', ['nacimiento', 'sexo']], [$r->estado, $r->cuerpo['error'], array_keys($r->cuerpo['campos'])], 'inválidos');
    $post($e, Escenario::datosAna());
    igual([409, 'PACIENTE_EXISTENTE'], [($x = $post($e, Escenario::datosAna()))->estado, $x->cuerpo['error']], 'repetido');
    igual(403, $post($e, Escenario::datosLuis(), [], 'token-clinico-oriente')->estado, 'clínico');
});

prueba('HTTP-04', 'GET /api/v1/pacientes?nombre=…: 200 con datos mínimos; criterio corto 422', function () use ($post): void {
    $e = Escenario::crear();
    $post($e, Escenario::datosAna());
    $r = $e->kernel->manejar(Escenario::http('GET', '/api/v1/pacientes', 'token-clinico-oriente', consulta: ['nombre' => 'lópez', 'nacimiento' => '1985', 'otro' => 'x']));
    igual(200, $r->estado, 'HTTP');
    igual([['codigo' => 'P-000001', 'nombre' => 'Ana Lucía López Pérez', 'nacimiento' => '1985-03-14', 'identificador' => '*********0101', 'identidad_protegida' => false]],
        $r->cuerpo['resultados'], 'resultados');
    $corto = $e->kernel->manejar(Escenario::http('GET', '/api/v1/pacientes', 'token-clinico-oriente', consulta: ['nombre' => 'lo']));
    igual([422, 'CRITERIO_INSUFICIENTE'], [$corto->estado, $corto->cuerpo['error']], 'criterio');
});

prueba('HTTP-05', 'GET /api/v1/pacientes/{codigo}: 200 con ETag; desde otro hospital el mismo 404 que uno inexistente', function () use ($post): void {
    $e = Escenario::crear();
    $post($e, Escenario::datosAna());
    $r = $e->kernel->manejar(Escenario::http('GET', '/api/v1/pacientes/P-000001', 'token-clinico-oriente'));
    igual([200, '"1"', 'female'], [$r->estado, $r->cabeceras['ETag'] ?? null, $r->cuerpo['paciente']['sexo']], 'detalle');
    $otro = $e->kernel->manejar(Escenario::http('GET', '/api/v1/pacientes/P-000001', 'token-admision-norte', hospital: Escenario::H_NORTE));
    $inexistente = $e->kernel->manejar(Escenario::http('GET', '/api/v1/pacientes/P-999999', 'token-admision-norte', hospital: Escenario::H_NORTE));
    igual([404, $inexistente->cuerpo], [$otro->estado, $otro->cuerpo], 'mismo 404');
});

prueba('HTTP-06', 'PUT: sin If-Match 428; If-Match viejo 409; If-Match vigente 200 con el nuevo ETag', function () use ($post): void {
    $e = Escenario::crear();
    $post($e, Escenario::datosAna());
    $put = static fn (?string $ifMatch, string $telefono): Respuesta => $e->kernel->manejar(Escenario::http('PUT', '/api/v1/pacientes/P-000001',
        cuerpo: Escenario::json(Escenario::datosAna(['telefono' => $telefono])), cabeceras: $ifMatch === null ? [] : ['If-Match' => $ifMatch]));

    igual([428, 'VERSION_REQUERIDA'], [($r = $put(null, '+502 4444 0001'))->estado, $r->cuerpo['error']], 'sin If-Match');
    igual(400, $put('abc', '+502 4444 0001')->estado, 'If-Match inválido');
    $ok = $put('"1"', '+502 4444 0001');
    igual([200, '"2"'], [$ok->estado, $ok->cabeceras['ETag'] ?? null], 'vigente');
    $viejo = $put('"1"', '+502 3333 0002');
    igual([409, 'VERSION_DESACTUALIZADA', 2], [$viejo->estado, $viejo->cuerpo['error'], $viejo->cuerpo['version_vigente']], 'viejo');
});

prueba('HTTP-07', 'Base de datos caída → 503 genérico, sin SQLSTATE ni detalles internos', function () use ($post): void {
    $e = Escenario::crear();
    $e->repositorio->fallarEn = ['registrar'];
    $r = $post($e, Escenario::datosAna());
    igual([503, ['error' => 'SERVICIO_NO_DISPONIBLE']], [$r->estado, $r->cuerpo], 'respuesta');
});

prueba('HTTP-08', 'Error inesperado → 500 genérico; ruta inexistente 404; método no permitido 405', function (): void {
    $explota = static fn (Solicitud $s, Identidad $i, array $p): Respuesta => throw new LogicException('detalle interno que no debe salir');
    $kernel = new Kernel([new Ruta('GET', '#^/explota$#', $explota)], new AutenticadorTokenDemo(['t' => ['a', Escenario::H_ORIENTE]]));
    $r = $kernel->manejar(Escenario::http('GET', '/explota', 't'));
    igual([500, ['error' => 'ERROR_INTERNO']], [$r->estado, $r->cuerpo], '500');

    $e = Escenario::crear();
    igual(404, $e->kernel->manejar(Escenario::http('GET', '/api/v1/otra-cosa'))->estado, 'ruta');
    igual(405, $e->kernel->manejar(Escenario::http('DELETE', '/api/v1/pacientes/P-000001'))->estado, 'método');
});

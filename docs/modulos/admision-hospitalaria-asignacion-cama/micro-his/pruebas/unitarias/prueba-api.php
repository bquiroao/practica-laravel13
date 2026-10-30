<?php
declare(strict_types=1);

// API REST v1 con el almacén InMemory: contrato, permisos, tenant del token, errores y X-Request-Id.

use Admision\Application\AdmitirPacienteYAsignarCama;
use Admision\Application\ConsultarAdmision;
use Admision\Application\ConsultarCamasDisponibles;
use Admision\Application\ConsultarTrazabilidad;
use Admision\Domain\Excepciones\ErrorPersistencia;
use Admision\Presentation\Api\ApiKernel;
use Admision\Presentation\Api\AutenticadorTokens;
use Admision\Presentation\Servicios;
use Admision\Presentation\UsuariosFicticios;
use Admision\Presentation\Web\Peticion;
use Pruebas\Afirmar;
use Pruebas\Datos;
use Pruebas\Dobles\AdmisionRepositoryEspia;
use Pruebas\Dobles\CatalogoCamasFalso;
use Pruebas\Dobles\DirectorioPacientesFalso;
use Pruebas\Dobles\GeneradorIdSecuencial;
use Pruebas\Dobles\RelojFijo;

$registro = [];
$api = function (?Servicios $servicios = null) use (&$registro): ApiKernel {
    $d = Datos::crudos();
    return new ApiKernel(
        $servicios ?? (require __DIR__ . '/../../bootstrap.php')(null, 'memoria'),
        new AutenticadorTokens($d['tokens'], new UsuariosFicticios($d['usuarios'])),
        new GeneradorIdSecuencial(),
        function (array $linea) use (&$registro) { $registro[] = $linea; },
    );
};
$pedir = fn (ApiKernel $k, string $metodo, string $ruta, ?string $token = 'tok-adm-01', array $cuerpo = [], array $extra = []) => $k->manejar(new Peticion(
    $metodo, parse_url($ruta, PHP_URL_PATH), (function () use ($ruta) { parse_str((string) parse_url($ruta, PHP_URL_QUERY), $q); return $q; })(), [],
    [...($token === null ? [] : ['authorization' => "Bearer {$token}"]), ...$extra], $cuerpo === [] ? '' : json_encode($cuerpo),
));
$admision = fn (string $paciente = 'PAC-00451', string $cama = Datos::B201) => [
    'codigo_paciente' => $paciente, 'servicio' => 'MEDICINA_INTERNA', 'tipo_cama' => 'GENERAL',
    'requiere_aislamiento' => false, 'origen' => 'EMERGENCIA', 'cama_id' => $cama,
];
$cuerpo = fn ($r) => json_decode($r->cuerpo, true);

return [
    'POST /admisiones: 201, Location, X-Request-Id y solo datos mínimos (CA-01, RNF-03)' => function () use ($api, $pedir, $admision, $cuerpo) {
        $r = $pedir($api(), 'POST', '/api/v1/admisiones', 'tok-adm-01', $admision(), ['x-request-id' => 'prueba-api-0001']);
        Afirmar::igual(201, $r->estado);
        Afirmar::igual('prueba-api-0001', $r->cabeceras['X-Request-Id']);
        $d = $cuerpo($r)['datos'];
        Afirmar::igual('/api/v1/admisiones/' . $d['id'], $r->cabeceras['Location']);
        Afirmar::igual(['id', 'codigo_paciente', 'estado', 'cama', 'servicio', 'origen', 'creada_por', 'creada_en'], array_keys($d));
        Afirmar::igual(['ACTIVA', 'B-201'], [$d['estado'], $d['cama']['codigo']]);
    },
    'Sin token o con token desconocido: 401 con WWW-Authenticate' => function () use ($api, $pedir, $cuerpo) {
        foreach ([null, 'tok-falso'] as $token) {
            $r = $pedir($api(), 'GET', '/api/v1/camas-disponibles?servicio=MEDICINA_INTERNA&tipo_cama=GENERAL', $token);
            Afirmar::igual([401, 'Bearer', 'NO_AUTENTICADO'], [$r->estado, $r->cabeceras['WWW-Authenticate'] ?? '', $cuerpo($r)['error']['codigo']]);
        }
        // D-02: sin token y con un parámetro inválido también debe ser 401, no 400.
        Afirmar::igual(401, $pedir($api(), 'GET', '/api/v1/camas-disponibles?requiere_aislamiento=si', null)->estado);
    },
    'MEDICO: no puede admitir (403) pero sí consultar la admisión (200) (CA-08, CA-10)' => function () use ($api, $pedir, $admision, $cuerpo) {
        $k = $api();
        Afirmar::igual(403, $pedir($k, 'POST', '/api/v1/admisiones', 'tok-med-01', $admision())->estado);
        $id = $cuerpo($pedir($k, 'POST', '/api/v1/admisiones', 'tok-adm-01', $admision()))['datos']['id'];
        Afirmar::igual(200, $pedir($k, 'GET', "/api/v1/admisiones/{$id}", 'tok-med-01')->estado);
    },
    'Tenant del token: HOSP-02 no ve HOSP-01 y no se acepta tenant_id en el cuerpo (CA-09)' => function () use ($api, $pedir, $admision, $cuerpo) {
        $k = $api();
        $id = $cuerpo($pedir($k, 'POST', '/api/v1/admisiones', 'tok-adm-01', $admision()))['datos']['id'];
        $r = $pedir($k, 'GET', "/api/v1/admisiones/{$id}", 'tok-adm-02');
        Afirmar::igual([404, 'ADMISION_NO_ENCONTRADA'], [$r->estado, $cuerpo($r)['error']['codigo']]);
        $r = $pedir($k, 'POST', '/api/v1/admisiones', 'tok-adm-02', [...$admision('PAC-00900'), 'tenant_id' => 'HOSP-01']);
        Afirmar::igual([400, ['tenant_id']], [$r->estado, $cuerpo($r)['error']['detalles']['campos']]);
    },
    'Cama ya asignada: 409 con el formato único de error (CA-07)' => function () use ($api, $pedir, $admision, $cuerpo) {
        $k = $api();
        $pedir($k, 'POST', '/api/v1/admisiones', 'tok-adm-01', $admision());
        $r = $pedir($k, 'POST', '/api/v1/admisiones', 'tok-sup-01', $admision('PAC-00452'));
        Afirmar::igual(409, $r->estado);
        $e = $cuerpo($r)['error'];
        Afirmar::igual(['codigo', 'mensaje', 'reintentable', 'request_id'], array_keys($e));
        Afirmar::igual(['CAMA_YA_ASIGNADA', false, $r->cabeceras['X-Request-Id']], [$e['codigo'], $e['reintentable'], $e['request_id']]);
    },
    'Petición mal formada: 400 por JSON, campos o parámetro inválido' => function () use ($api, $pedir, $admision, $cuerpo) {
        $k = $api();
        $r = $k->manejar(new Peticion('POST', '/api/v1/admisiones', [], [], ['authorization' => 'Bearer tok-adm-01'], '{no es json'));
        Afirmar::igual([400, 'SOLICITUD_INVALIDA'], [$r->estado, $cuerpo($r)['error']['codigo']]);
        $sinCama = $admision();
        unset($sinCama['cama_id']);
        $sinCama['requiere_aislamiento'] = 'no';
        Afirmar::igual(['cama_id', 'requiere_aislamiento'], $cuerpo($pedir($k, 'POST', '/api/v1/admisiones', 'tok-adm-01', $sinCama))['error']['detalles']['campos']);
        Afirmar::igual(400, $pedir($k, 'GET', '/api/v1/camas-disponibles?servicio=MEDICINA_INTERNA&tipo_cama=GENERAL&requiere_aislamiento=si')->estado);
    },
    'Cama no compatible: 422 con los motivos (RN-03)' => function () use ($api, $pedir, $admision, $cuerpo) {
        $r = $pedir($api(), 'POST', '/api/v1/admisiones', 'tok-adm-01', $admision('PAC-00451', Datos::B210));
        Afirmar::igual([422, ['La cama de aislamiento se reserva para quien la requiere.']], [$r->estado, $cuerpo($r)['error']['detalles']['motivos']]);
    },
    'Error de persistencia: 503 reintentable, sin detalles técnicos (CA-12)' => function () use ($api, $pedir, $admision, $cuerpo) {
        $repo = new AdmisionRepositoryEspia();
        $repo->fallaAlRegistrar = new ErrorPersistencia('No se pudo registrar la admisión. No se guardó ningún dato.');
        $servicios = new Servicios(
            new AdmitirPacienteYAsignarCama(new DirectorioPacientesFalso(), new CatalogoCamasFalso(), $repo, Datos::evaluador(), new RelojFijo(), new GeneradorIdSecuencial()),
            new ConsultarCamasDisponibles(new CatalogoCamasFalso(), $repo, Datos::evaluador()), new ConsultarAdmision($repo), new ConsultarTrazabilidad($repo),
        );
        $r = $pedir($api($servicios), 'POST', '/api/v1/admisiones', 'tok-adm-01', $admision());
        Afirmar::igual([503, true], [$r->estado, $cuerpo($r)['error']['reintentable']]);
        Afirmar::verdadero(!str_contains($r->cuerpo, 'SQLSTATE'), 'no filtra detalles técnicos');
    },
    'X-Request-Id inválido se reemplaza; el registro no contiene datos del paciente (RNF-05, RNF-03)' => function () use ($api, $pedir, $admision, &$registro) {
        $registro = [];
        $r = $pedir($api(), 'POST', '/api/v1/admisiones', 'tok-adm-01', $admision(), ['x-request-id' => "mal id\n"]);
        Afirmar::verdadero(str_starts_with($r->cabeceras['X-Request-Id'], 'req-'), 'se genera un id nuevo');
        Afirmar::igual($r->cabeceras['X-Request-Id'], $registro[0]['request_id']);
        Afirmar::igual(201, $registro[0]['estado']);
        Afirmar::verdadero(!str_contains(json_encode($registro), 'PAC-'), 'el registro no tiene códigos de paciente');
    },
    'Trazabilidad por API: supervisor 200 en orden; médico 403 (CA-11)' => function () use ($api, $pedir, $admision, $cuerpo) {
        $k = $api();
        $id = $cuerpo($pedir($k, 'POST', '/api/v1/admisiones', 'tok-adm-01', $admision(), ['x-request-id' => 'prueba-api-0002']))['datos']['id'];
        $r = $pedir($k, 'GET', "/api/v1/admisiones/{$id}/eventos", 'tok-sup-01');
        Afirmar::igual(['ADMISION_CREADA', 'CAMA_ASIGNADA'], array_column($cuerpo($r)['datos'], 'tipo'));
        Afirmar::igual('prueba-api-0002', $cuerpo($r)['datos'][0]['solicitud_id']);
        Afirmar::igual(403, $pedir($k, 'GET', "/api/v1/admisiones/{$id}/eventos", 'tok-med-01')->estado);
    },
];

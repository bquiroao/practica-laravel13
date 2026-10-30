<?php
declare(strict_types=1);

// API REST JSON sobre los mismos casos de uso (adaptador InMemory, reloj fijo en 2026-10-15).

use Catalogo\Application\RegistrarMedicamento;
use Catalogo\Presentation\Api\Autenticador;
use Catalogo\Presentation\Api\KernelApi;
use Catalogo\Presentation\Web\Peticion;

require_once __DIR__ . '/../../bootstrap.php';

$config = require __DIR__ . '/../../config.php';
$registro = [];
$api = function (string $metodo, string $ruta, ?string $token = 'tok-qf-norte-0001', mixed $cuerpo = null, array $extra = [], ?array $app = null) use ($config, &$registro) {
    $cabeceras = $extra + ($token === null ? [] : ['authorization' => "Bearer $token"]);
    $crudo = '';
    if ($cuerpo !== null) {
        $cabeceras += ['content-type' => 'application/json'];
        $crudo = is_string($cuerpo) ? $cuerpo : json_encode($cuerpo);
    }
    [$ruta, $consulta] = array_pad(explode('?', $ruta, 2), 2, '');
    parse_str($consulta, $q);
    $kernel = new KernelApi($app ?? crearAplicacion(null, new RelojFijo(), 'memoria'), new Autenticador($config['api']['tokens']), function (string $l) use (&$registro) { $registro[] = $l; });
    $r = $kernel->manejar(new Peticion($metodo, $ruta, $q, [], $cabeceras, $crudo));
    return [$r->estado, json_decode($r->cuerpo, true), $r->cabeceras];
};
$nuevo = Fabrica::datos(['nombre' => 'Loratadina 10 mg tableta', 'principio_activo' => 'Loratadina', 'concentracion_valor' => '10', 'forma_farmaceutica' => 'TABLETA', 'vigente_desde' => '']);

return [
    'GET búsqueda 200 con datos y meta (CU-02)' => function () use ($api) {
        [$estado, $json] = $api('GET', '/api/v1/medicamentos?q=amoxi', 'tok-consulta-norte-0001');
        Afirmar::igual(200, $estado, 'estado');
        Afirmar::igual(['MED-000103', 'MED-000102'], array_column($json['datos'], 'codigo'), 'códigos');
        Afirmar::igual(['total' => 2, 'fecha' => Fabrica::HOY, 'limite' => 50], $json['meta'], 'meta');
    },
    'Sin token 401 y token desconocido 401 con formato único de error' => function () use ($api) {
        [$e1, $j1, $c1] = $api('GET', '/api/v1/medicamentos?q=amoxi', null);
        [$e2] = $api('GET', '/api/v1/medicamentos?q=amoxi', 'tok-inventado-9999');
        Afirmar::igual([401, 401], [$e1, $e2], 'estados');
        Afirmar::igual(['codigo', 'mensaje', 'detalles', 'request_id'], array_keys($j1['error']), 'formato del error');
        Afirmar::igual($c1['X-Request-Id'], $j1['error']['request_id'], 'request_id = cabecera');
    },
    'RNF-01 permisos por rol: consulta clínica no registra (403); auditor ve historial' => function () use ($api, $nuevo) {
        [$e1, $j1] = $api('POST', '/api/v1/medicamentos', 'tok-consulta-norte-0001', $nuevo);
        Afirmar::igual([403, 'PROHIBIDO'], [$e1, $j1['error']['codigo']], 'consulta clínica');
        Afirmar::igual(200, $api('GET', '/api/v1/medicamentos/MED-000104/versiones', 'tok-auditor-norte-0001')[0], 'auditor');
        Afirmar::igual(403, $api('GET', '/api/v1/medicamentos/MED-000104/versiones', 'tok-prescripciones-norte-0001')[0], 'sistema de prescripciones');
        Afirmar::igual(200, $api('GET', '/api/v1/medicamentos/MED-000101', 'tok-prescripciones-norte-0001')[0], 'pero sí consulta por código');
    },
    'RNF-02 el tenant sale del token: el Sur no ve MED-000102 (404) y hospital_id del cuerpo se ignora' => function () use ($api, $nuevo) {
        Afirmar::igual(404, $api('GET', '/api/v1/medicamentos/MED-000102', 'tok-qf-sur-0001')[0], 'otro hospital');
        [$estado, $json] = $api('POST', '/api/v1/medicamentos', 'tok-qf-sur-0001', ['hospital_id' => Fabrica::NORTE] + $nuevo);
        Afirmar::igual([201, 'MED-000102'], [$estado, $json['datos']['codigo']], 'se registró en el Sur (su siguiente código)');
    },
    'POST alta 201 con Location (CA-01)' => function () use ($api, $nuevo) {
        [$estado, $json, $cab] = $api('POST', '/api/v1/medicamentos', 'tok-qf-norte-0001', $nuevo);
        Afirmar::igual(201, $estado, 'estado');
        Afirmar::igual('/api/v1/medicamentos/MED-000107', $cab['Location'], 'Location');
        Afirmar::igual(Fabrica::HOY, $json['datos']['vigente_desde'], 'vigente desde hoy');
    },
    '422 con detalles por campo y 409 DUPLICADO con código existente (CA-02, CA-04)' => function () use ($api, $nuevo) {
        [$e1, $j1] = $api('POST', '/api/v1/medicamentos', 'tok-qf-norte-0001', ['nombre' => str_repeat('x', 151)] + $nuevo);
        Afirmar::igual([422, 'VALIDACION'], [$e1, $j1['error']['codigo']], '422');
        Afirmar::verdadero(isset($j1['error']['detalles']['nombre']), 'detalle del campo nombre');
        [$e2, $j2] = $api('POST', '/api/v1/medicamentos', 'tok-qf-norte-0001', Fabrica::datos(['vigente_desde' => '']));
        Afirmar::igual([409, 'DUPLICADO', 'MED-000102'], [$e2, $j2['error']['codigo'], $j2['error']['detalles']['codigo_existente']], '409');
    },
    'Versión 201, baja 200, eliminar usado 409 y sin uso 204' => function () use ($api) {
        $app = crearAplicacion(null, new RelojFijo(), 'memoria');
        $version = Fabrica::datos(['presentacion' => 'Frasco con 30 cápsulas', 'vigente_desde' => '2026-11-01']);
        Afirmar::igual(201, $api('POST', '/api/v1/medicamentos/MED-000102/versiones', 'tok-qf-norte-0001', $version, [], $app)[0], 'versión');
        [$e, $j] = $api('POST', '/api/v1/medicamentos/MED-000103/baja', 'tok-qf-norte-0001', ['motivo' => 'Retiro ficticio de prueba'], [], $app);
        Afirmar::igual([200, Fabrica::HOY], [$e, $j['datos']['vigente_hasta']], 'baja');
        Afirmar::igual(409, $api('DELETE', '/api/v1/medicamentos/MED-000101', 'tok-qf-norte-0001', null, [], $app)[0], 'usado');
        Afirmar::igual(204, $api('DELETE', '/api/v1/medicamentos/MED-000106', 'tok-qf-norte-0001', null, [], $app)[0], 'sin uso');
        Afirmar::igual(404, $api('GET', '/api/v1/medicamentos/MED-000106', 'tok-qf-norte-0001', null, [], $app)[0], 'ya no existe');
    },
    'Errores de protocolo: 415, 400, 404 de ruta y 405' => function () use ($api, $nuevo) {
        Afirmar::igual(415, $api('POST', '/api/v1/medicamentos', 'tok-qf-norte-0001', null, ['content-type' => 'text/plain'])[0], '415');
        Afirmar::igual(400, $api('POST', '/api/v1/medicamentos', 'tok-qf-norte-0001', '{"nombre": ')[0], 'JSON roto');
        Afirmar::igual(400, $api('POST', '/api/v1/medicamentos', 'tok-qf-norte-0001', '[1,2]')[0], 'lista en vez de objeto');
        Afirmar::igual(404, $api('GET', '/api/v1/inexistente')[0], 'ruta');
        [$e, $j, $c] = $api('PUT', '/api/v1/medicamentos');
        Afirmar::igual([405, 'METODO_NO_PERMITIDO', 'GET, POST'], [$e, $j['error']['codigo'], $c['Allow']], '405');
    },
    'Error de persistencia: 503 con Retry-After y sin detalle técnico (CA-13)' => function () use ($api, $nuevo) {
        $app = crearAplicacion(null, new RelojFijo(), 'memoria');
        $lectura = new CatalogoFalso();
        $app['registrar'] = new RegistrarMedicamento($lectura, new EscrituraQueFalla(), new IdsSecuenciales(), new RelojFijo());
        [$estado, $json, $cab] = $api('POST', '/api/v1/medicamentos', 'tok-qf-norte-0001', $nuevo, [], $app);
        Afirmar::igual([503, 'PERSISTENCIA', '5'], [$estado, $json['error']['codigo'], $cab['Retry-After']], '503');
        Afirmar::verdadero(!str_contains(json_encode($json), 'conexión rechazada'), 'sin detalle técnico en la respuesta');
    },
    'Observabilidad: X-Request-Id del cliente se reutiliza y el registro no lleva el token' => function () use ($api, &$registro) {
        $registro = [];
        [, , $cab] = $api('GET', '/api/v1/medicamentos?q=amoxi', 'tok-qf-norte-0001', null, ['x-request-id' => 'prueba-req-0001']);
        Afirmar::igual('prueba-req-0001', $cab['X-Request-Id'], 'cabecera');
        $linea = json_decode(end($registro), true);
        Afirmar::igual(['prueba-req-0001', 200, 'qf.norte'], [$linea['request_id'], $linea['estado'], $linea['usuario']], 'línea de registro');
        Afirmar::verdadero(!str_contains(implode("\n", $registro), 'tok-qf-norte'), 'el token no se registra');
        [, , $cab2] = $api('GET', '/api/v1/opciones', 'tok-qf-norte-0001', null, ['x-request-id' => "inyección\nmala"]);
        Afirmar::verdadero(preg_match('/^[0-9a-f]{16}$/', $cab2['X-Request-Id']) === 1, 'un id inseguro se reemplaza');
    },
];

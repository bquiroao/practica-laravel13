<?php
declare(strict_types=1);

// Semana 15 · Caja negra sobre la API (sin mirar el código): clases de equivalencia, valores
// límite, permisos y errores. Adaptador InMemory y reloj fijo en 2026-10-15.

use Catalogo\Presentation\Api\Autenticador;
use Catalogo\Presentation\Api\KernelApi;
use Catalogo\Presentation\Web\Peticion;

require_once __DIR__ . '/../../bootstrap.php';

$config = require __DIR__ . '/../../config.php';
$llamar = function (string $metodo, string $ruta, ?string $token = 'tok-qf-norte-0001', ?array $cuerpo = null, ?array $app = null) use ($config): array {
    [$camino, $consulta] = array_pad(explode('?', $ruta, 2), 2, '');
    parse_str($consulta, $q);
    $cab = ($token === null ? [] : ['authorization' => "Bearer $token"]) + ($cuerpo === null ? [] : ['content-type' => 'application/json']);
    $kernel = new KernelApi($app ?? crearAplicacion(null, new RelojFijo(), 'memoria'), new Autenticador($config['api']['tokens']), fn () => null);
    $r = $kernel->manejar(new Peticion($metodo, $camino, $q, [], $cab, $cuerpo === null ? '' : (string) json_encode($cuerpo)));
    return [$r->estado, $r->cuerpo, $r->cabeceras];
};
$alta = fn (array $cambios) => $cambios + Fabrica::datos(['principio_activo' => 'Loratadina', 'forma_farmaceutica' => 'TABLETA', 'vigente_desde' => '']);

return [
    'CN-01 RF-02 nombre: límites 2, 3, 150 y 151 caracteres (clases inválida / válida / válida / inválida)' => function () use ($llamar, $alta) {
        $obtenido = [];
        foreach ([2, 3, 150, 151] as $largo) {
            $obtenido[$largo] = $llamar('POST', '/api/v1/medicamentos', 'tok-qf-norte-0001', $alta(['nombre' => str_repeat('n', $largo)]))[0];
        }
        Afirmar::igual([2 => 422, 3 => 201, 150 => 201, 151 => 422], $obtenido, 'estados por largo');
    },
    'CN-02 RF-02 concentración: 0, 0.001, 999999.999, 1000000, 1.2345 y texto' => function () use ($llamar, $alta) {
        $obtenido = [];
        foreach (['0', '0.001', '999999.999', '1000000', '1.2345', 'diez'] as $valor) {
            $obtenido[$valor] = $llamar('POST', '/api/v1/medicamentos', 'tok-qf-norte-0001', $alta(['concentracion_valor' => $valor]))[0];
        }
        Afirmar::igual(['0' => 422, '0.001' => 201, '999999.999' => 201, '1000000' => 422, '1.2345' => 422, 'diez' => 422], $obtenido, 'estados por valor');
    },
    'CN-03 RF-04 búsqueda: texto de 1, 2, 60 y 61 caracteres y fechas válida e inválida' => function () use ($llamar) {
        $q = fn (string $t, string $extra = '') => $llamar('GET', '/api/v1/medicamentos?q=' . rawurlencode($t) . $extra, 'tok-consulta-norte-0001')[0];
        Afirmar::igual([422, 200, 200, 422], [$q('a'), $q('am'), $q(str_repeat('a', 60)), $q(str_repeat('a', 61))], 'largos');
        Afirmar::igual([200, 422, 422], [$q('ibupro', '&fecha=2026-03-10'), $q('ibupro', '&fecha=2026-02-30'), $q('ibupro', '&fecha=1999-12-31')], 'fechas');
    },
    'CN-04 RNF-01 CA-12 matriz de permisos: 4 roles × buscar, historial y registrar' => function () use ($llamar, $alta) {
        $acciones = [
            'buscar' => fn ($t) => $llamar('GET', '/api/v1/medicamentos?q=amoxi', $t)[0],
            'historial' => fn ($t) => $llamar('GET', '/api/v1/medicamentos/MED-000104/versiones', $t)[0],
            'registrar' => fn ($t) => $llamar('POST', '/api/v1/medicamentos', $t, $alta(['nombre' => 'Loratadina 10 mg tableta']))[0],
        ];
        $esperado = [
            'tok-qf-norte-0001' => [200, 200, 201], 'tok-consulta-norte-0001' => [200, 403, 403],
            'tok-auditor-norte-0001' => [200, 200, 403], 'tok-prescripciones-norte-0001' => [200, 403, 403],
        ];
        foreach ($esperado as $token => $estados) {
            Afirmar::igual($estados, array_values(array_map(fn ($a) => $a($token), $acciones)), $token);
        }
    },
    'CN-05 RNF-02 CA-12 tenant: el Sur recibe 404 en códigos del Norte y no los ve en la búsqueda' => function () use ($llamar) {
        Afirmar::igual(404, $llamar('GET', '/api/v1/medicamentos/MED-000104', 'tok-qf-sur-0001')[0], 'consulta por código');
        Afirmar::igual(404, $llamar('DELETE', '/api/v1/medicamentos/MED-000106', 'tok-qf-sur-0001')[0], 'eliminar');
        $json = json_decode($llamar('GET', '/api/v1/medicamentos?q=amoxi', 'tok-qf-sur-0001')[1], true);
        Afirmar::igual([], $json['datos'], 'búsqueda vacía en el Sur');
    },
    'CN-06 RNF-05 toda respuesta lleva X-Request-Id, también 401, 404, 409 y 204' => function () use ($llamar, $alta) {
        $respuestas = [
            $llamar('GET', '/api/v1/opciones', null),
            $llamar('GET', '/api/v1/medicamentos/MED-000999'),
            $llamar('POST', '/api/v1/medicamentos', 'tok-qf-norte-0001', Fabrica::datos(['vigente_desde' => ''])),
            $llamar('DELETE', '/api/v1/medicamentos/MED-000106'),
        ];
        foreach ($respuestas as [$estado, , $cab]) {
            Afirmar::verdadero(isset($cab['X-Request-Id']) && $cab['X-Request-Id'] !== '', "X-Request-Id en el $estado");
        }
    },
    'CN-07 RNF-08 un error inesperado responde 500 INTERNO sin SQL ni trazas' => function () use ($llamar) {
        $app = crearAplicacion(null, new RelojFijo(), 'memoria');
        $app['buscar'] = new class {
            public function ejecutar(): never
            {
                throw new RuntimeException('SQLSTATE[42P01]: relation "medicamentos" does not exist at /srv/app.php:12');
            }
        };
        [$estado, $cuerpo] = $llamar('GET', '/api/v1/medicamentos?q=amoxi', 'tok-qf-norte-0001', null, $app);
        Afirmar::igual(500, $estado, 'estado');
        Afirmar::verdadero(!str_contains($cuerpo, 'SQLSTATE') && !str_contains($cuerpo, '.php'), 'sin detalle técnico');
        Afirmar::igual('INTERNO', json_decode($cuerpo, true)['error']['codigo'], 'código');
    },
    'CN-08 RF-08 errores del flujo: eliminar usado 409 EN_USO; baja repetida 409 YA_DADO_DE_BAJA' => function () use ($llamar) {
        $app = crearAplicacion(null, new RelojFijo(), 'memoria');
        Afirmar::igual(409, $llamar('DELETE', '/api/v1/medicamentos/MED-000101', 'tok-qf-norte-0001', null, $app)[0], 'eliminar usado');
        $baja = ['motivo' => 'Retiro ficticio de prueba'];
        Afirmar::igual(200, $llamar('POST', '/api/v1/medicamentos/MED-000103/baja', 'tok-qf-norte-0001', $baja, $app)[0], 'primera baja');
        [$estado, $cuerpo] = $llamar('POST', '/api/v1/medicamentos/MED-000103/baja', 'tok-qf-norte-0001', $baja, $app);
        Afirmar::igual([409, 'YA_DADO_DE_BAJA'], [$estado, json_decode($cuerpo, true)['error']['codigo']], 'segunda baja');
    },
];

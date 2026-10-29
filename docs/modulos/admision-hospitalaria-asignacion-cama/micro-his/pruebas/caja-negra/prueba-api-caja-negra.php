<?php
declare(strict_types=1);

// Semana 15 — CAJA NEGRA contra la API v1 (solo entradas y salidas del contrato; sin mirar el código).
// Clases de equivalencia, valores límite, permisos y errores. Almacén InMemory: no prueba módulos ajenos.

use Admision\Application\Puertos\GeneradorUuidV4;
use Admision\Presentation\Api\ApiKernel;
use Admision\Presentation\Api\AutenticadorTokens;
use Admision\Presentation\UsuariosFicticios;
use Admision\Presentation\Web\Peticion;
use Pruebas\Afirmar;
use Pruebas\Datos;

$api = function (): ApiKernel {
    $d = Datos::crudos();
    return new ApiKernel((require __DIR__ . '/../../bootstrap.php')(null, 'memoria'),
        new AutenticadorTokens($d['tokens'], new UsuariosFicticios($d['usuarios'])), new GeneradorUuidV4(), fn (array $r) => null);
};
$llamar = function (ApiKernel $k, string $metodo, string $ruta, ?string $token, ?array $cuerpo = null, array $consulta = []): array {
    $r = $k->manejar(new Peticion($metodo, $ruta, $consulta, [], $token === null ? [] : ['authorization' => "Bearer {$token}"], $cuerpo === null ? '' : json_encode($cuerpo)));
    return [$r->estado, json_decode($r->cuerpo, true)['error']['codigo'] ?? null, json_decode($r->cuerpo, true)['datos'] ?? null];
};
$admision = fn (string $paciente, string $cama = Datos::B201) => ['codigo_paciente' => $paciente, 'servicio' => 'MEDICINA_INTERNA',
    'tipo_cama' => 'GENERAL', 'requiere_aislamiento' => false, 'origen' => 'EMERGENCIA', 'cama_id' => $cama];
$crear = fn (string $paciente, string $token = 'tok-adm-01', string $cama = Datos::B201) => $llamar($api(), 'POST', '/api/v1/admisiones', $token, $admision($paciente, $cama));

return [
    'CN-01 Equivalencia del código de paciente: válido, formato inválido, inexistente, inactivo y de otro hospital (RF-02)' => function () use ($crear) {
        Afirmar::igual(201, $crear('PAC-00451')[0]);
        Afirmar::igual([422, 'VALIDACION'], array_slice($crear('pac-00451'), 0, 2));
        Afirmar::igual([404, 'PACIENTE_NO_ENCONTRADO'], array_slice($crear('PAC-00999'), 0, 2));
        Afirmar::igual([404, 'PACIENTE_NO_ENCONTRADO'], array_slice($crear('PAC-00460'), 0, 2));
        Afirmar::igual([404, 'PACIENTE_NO_ENCONTRADO'], array_slice($crear('PAC-00900'), 0, 2));
    },
    'CN-02 Límites del código: 4, 5 y 6 dígitos; PAC-00000 con formato válido pero inexistente (RF-02)' => function () use ($crear) {
        Afirmar::igual(422, $crear('PAC-0045')[0]);
        Afirmar::igual(201, $crear('PAC-00451')[0]);
        Afirmar::igual(422, $crear('PAC-004510')[0]);
        Afirmar::igual(404, $crear('PAC-00000')[0]);
    },
    'CN-03 Permisos: matriz rol × endpoint (RN-06, RF-08, CA-08, CA-11)' => function () use ($api, $llamar, $admision) {
        $k = $api();
        $id = $llamar($k, 'POST', '/api/v1/admisiones', 'tok-sup-01', $admision('PAC-00451'))[2]['id'];
        $camas = ['servicio' => 'MEDICINA_INTERNA', 'tipo_cama' => 'GENERAL'];
        $esperado = [ // token => [camas, crear, ver, eventos]
            'tok-adm-01' => [200, 409, 200, 403],
            'tok-sup-01' => [200, 409, 200, 200],
            'tok-med-01' => [403, 403, 200, 403],
        ];
        foreach ($esperado as $token => $estados) {
            Afirmar::igual($estados, [
                $llamar($k, 'GET', '/api/v1/camas-disponibles', $token, null, $camas)[0],
                $llamar($k, 'POST', '/api/v1/admisiones', $token, $admision('PAC-00451'))[0], // 409: PAC-00451 ya está admitido
                $llamar($k, 'GET', "/api/v1/admisiones/{$id}", $token)[0],
                $llamar($k, 'GET', "/api/v1/admisiones/{$id}/eventos", $token)[0],
            ], $token);
        }
    },
    'CN-04 Errores del contrato: 401, 400, 409, 422, 404 y 405 (RF-05, RNF-02, RNF-08)' => function () use ($api, $llamar, $admision) {
        $k = $api();
        Afirmar::igual([401, 'NO_AUTENTICADO'], array_slice($llamar($k, 'POST', '/api/v1/admisiones', null, $admision('PAC-00451')), 0, 2));
        Afirmar::igual([400, 'SOLICITUD_INVALIDA'], array_slice($llamar($k, 'POST', '/api/v1/admisiones', 'tok-adm-01', ['codigo_paciente' => 'PAC-00451']), 0, 2));
        $llamar($k, 'POST', '/api/v1/admisiones', 'tok-adm-01', $admision('PAC-00451'));
        Afirmar::igual([409, 'CAMA_YA_ASIGNADA'], array_slice($llamar($k, 'POST', '/api/v1/admisiones', 'tok-adm-01', $admision('PAC-00452')), 0, 2));
        Afirmar::igual([422, 'CAMA_NO_ELEGIBLE'], array_slice($llamar($k, 'POST', '/api/v1/admisiones', 'tok-adm-01', $admision('PAC-00453', Datos::B203)), 0, 2));
        Afirmar::igual([404, 'RECURSO_NO_ENCONTRADO'], array_slice($llamar($k, 'GET', '/api/v1/pacientes', 'tok-adm-01'), 0, 2));
        Afirmar::igual([405, 'METODO_NO_PERMITIDO'], array_slice($llamar($k, 'DELETE', '/api/v1/admisiones', 'tok-adm-01'), 0, 2));
    },
    'CN-05 Equivalencia de requiere_aislamiento: true, false, ausente e inválido (RF-03)' => function () use ($api, $llamar) {
        $k = $api();
        $base = ['servicio' => 'MEDICINA_INTERNA', 'tipo_cama' => 'GENERAL'];
        $codigos = fn (array $q) => array_column($llamar($k, 'GET', '/api/v1/camas-disponibles', 'tok-adm-01', null, $q)[2] ?? [], 'codigo');
        Afirmar::igual(['B-210'], $codigos([...$base, 'requiere_aislamiento' => 'true']));
        Afirmar::igual(['B-201', 'B-202'], $codigos([...$base, 'requiere_aislamiento' => 'false']));
        Afirmar::igual(['B-201', 'B-202'], $codigos($base));
        Afirmar::igual(400, $llamar($k, 'GET', '/api/v1/camas-disponibles', 'tok-adm-01', null, [...$base, 'requiere_aislamiento' => '1'])[0]);
    },
    '[DEFECTO D-05] Falta el parámetro servicio en GET /camas-disponibles: se espera 400 SOLICITUD_INVALIDA' => function () use ($api, $llamar) {
        Afirmar::igual([400, 'SOLICITUD_INVALIDA'], array_slice($llamar($api(), 'GET', '/api/v1/camas-disponibles', 'tok-adm-01', null, ['tipo_cama' => 'GENERAL']), 0, 2));
    },
];

<?php
declare(strict_types=1);

// API REST con InMemory: estados, formato de error y permisos del contrato.
use Lab\Persistence\InMemoryLabTestRepository;

$api = static fn () => crearApi(['tenant' => 'HOSP-01'], new InMemoryLabTestRepository());
$jefe = ['authorization' => 'Bearer tok-jefe'];
$ruta = '/api/v1/laboratorio/pruebas';
$json = static fn (string $archivo, array $cambios = []): string => json_encode(datos($archivo, $cambios));

return [
    'T-13 API: POST con rol jefe de laboratorio responde 201 con requisitos y vigencia' => function () use ($api, $jefe, $ruta, $json): void {
        $r = $api()->despachar('POST', $ruta, $jefe, [], $json('hem-001'));
        afirmar($r->estado === 201 && $r->cuerpo['data']['requisitos_muestra']['volumen_minimo_ml'] === 3.0, 'Se esperaba 201 con 3 mL');
        afirmar($r->cuerpo['data']['vigencia'] === ['desde' => '2027-01-04', 'hasta' => null], 'Vigencia incorrecta');
    },
    'T-14 API: sin token 401 y médico intentando registrar 403 (RN-01)' => function () use ($api, $ruta, $json): void {
        afirmar($api()->despachar('POST', $ruta, [], [], $json('hem-001'))->estado === 401, 'Sin token debe ser 401');
        $r = $api()->despachar('POST', $ruta, ['authorization' => 'Bearer tok-medico'], [], $json('hem-001'));
        afirmar($r->estado === 403 && $r->cuerpo['error']['codigo'] === 'SIN_PERMISO', 'Médico debe recibir 403');
    },
    'T-15 API: 422 con campos y 409 por vigencia superpuesta' => function () use ($api, $jefe, $ruta, $json): void {
        $a = $api();
        $r = $a->despachar('POST', $ruta, $jefe, [], $json('glu-001-v1', ['estabilidad_horas' => 0]));
        afirmar($r->estado === 422 && isset($r->cuerpo['error']['campos']['estabilidad_horas']), 'Se esperaba 422 en estabilidad_horas');
        $a->despachar('POST', $ruta, $jefe, [], $json('glu-001-v1'));
        afirmar($a->despachar('POST', $ruta, $jefe, [], $json('glu-001-v1'))->estado === 409, 'Se esperaba 409');
    },
    'T-16 API: GET vigentes para el médico y 404 con vigencia en el detalle' => function () use ($api, $jefe, $ruta, $json): void {
        $a = $api();
        $a->despachar('POST', $ruta, $jefe, [], $json('hem-001'));
        $medico = ['authorization' => 'Bearer tok-medico'];
        $lista = $a->despachar('GET', $ruta, $medico, ['fecha' => '2027-01-15'], '');
        afirmar($lista->estado === 200 && count($lista->cuerpo['data']) === 1, 'Se esperaba 1 prueba vigente');
        $r = $a->despachar('GET', "$ruta/HEM-001", $medico, ['fecha' => '2027-01-03'], '');
        afirmar($r->estado === 404 && $r->cuerpo['error']['vigencia']['desde'] === '2027-01-04', 'Se esperaba 404 con vigencia');
    },
];

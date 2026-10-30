<?php
declare(strict_types=1);

// Semana 5: API REST v1 en proceso (mismo KernelApi que usa public/index.php).
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/base.php';

/** Devuelve [estado, json, cabeceras, registros de acceso]. */
$llamar = function (string $metodo, string $ruta, ?string $token, array $cuerpo = [], array $extra = [], ?PDO $pdo = null): array {
    $accesos = [];
    $api = armarApi(armarCasosDeUso($pdo ?? baseDePrueba()), function (array $l) use (&$accesos) { $accesos[] = $l; });
    $cabeceras = $extra + ($token ? ['authorization' => "Bearer {$token}"] : []);
    $r = $api->manejar($metodo, $ruta, $cabeceras, $cuerpo === [] ? '' : json_encode($cuerpo));
    return [$r->estado, json_decode($r->cuerpo, true), $r->cabeceras, $accesos];
};
$penicilina = ['alergeno_nombre' => 'Penicilina', 'tipo' => 'allergy', 'severidad' => 'life_threatening', 'reaccion' => 'Edema de glotis', 'fecha_inicio' => '2019-03-02'];
$ALERTA = '/api/v1/pacientes/PAC-00451/alerta';
$ALERGIAS = '/api/v1/pacientes/PAC-00451/alergias';

return [
    'T-35 enfermería consulta la alerta: 200, JSON con nivel y X-Request-Id' => function () use ($llamar, $ALERTA) {
        [$estado, $json, $cab] = $llamar('GET', $ALERTA, 'tok-enf-h01');
        Arnes::igual([200, 'MODERADA'], [$estado, $json['nivel']], 'estado y nivel');
        Arnes::afirmar(isset($cab['X-Request-Id']), 'falta X-Request-Id');
    },
    'T-36 sin token: 401 con el formato único de error' => function () use ($llamar, $ALERTA) {
        [$estado, $json] = $llamar('GET', $ALERTA, null);
        Arnes::igual(401, $estado, 'estado');
        Arnes::igual(['codigo', 'mensaje', 'request_id'], array_keys($json['error']), 'formato de error');
    },
    'T-37 enfermería intenta registrar: 403 PERMISO_DENEGADO y no se guarda nada' => function () use ($llamar, $ALERGIAS, $penicilina) {
        $pdo = baseDePrueba();
        [$estado, $json] = $llamar('POST', $ALERGIAS, 'tok-enf-h01', $penicilina, [], $pdo);
        Arnes::igual([403, 'PERMISO_DENEGADO'], [$estado, $json['error']['codigo']], 'respuesta');
        Arnes::igual(1, contarAlergias($pdo), 'solo la alergia demo');
    },
    'T-38 tenant: token de H-02 → 403 aunque el encabezado diga H-01; admisión sin permiso → 403' => function () use ($llamar, $ALERTA) {
        [$estado, $json] = $llamar('GET', $ALERTA, 'tok-med-h02', [], ['x-tenant-id' => 'H-01']);
        Arnes::igual([403, 'TENANT_DISTINTO'], [$estado, $json['error']['codigo']], 'tenant');
        Arnes::igual(403, $llamar('GET', $ALERTA, 'tok-adm-h01')[0], 'admisión');
    },
    'T-39 médico registra: 201, Location y alerta CRITICA que requiere reconocimiento' => function () use ($llamar, $ALERGIAS, $penicilina) {
        [$estado, $json, $cab] = $llamar('POST', $ALERGIAS, 'tok-med-h01', $penicilina);
        Arnes::igual([201, 'CRITICA', true], [$estado, $json['nivel'], $json['requiere_reconocimiento']], 'respuesta');
        Arnes::igual('/api/v1/pacientes/PAC-00451/alerta', $cab['Location'] ?? null, 'Location');
    },
    'T-40 JSON mal formado → 400 FORMATO_INVALIDO; regla de dominio → 422 con su código' => function () use ($llamar, $ALERGIAS) {
        $api = armarApi(armarCasosDeUso(baseDePrueba()), fn () => null);
        $r = $api->manejar('POST', $ALERGIAS, ['authorization' => 'Bearer tok-med-h01'], '{"alergeno_nombre": ');
        Arnes::igual(400, $r->estado, 'JSON roto');
        [$estado, $json] = $llamar('POST', $ALERGIAS, 'tok-med-h01', ['alergeno_nombre' => 'Látex', 'severidad' => 'grave']);
        Arnes::igual([422, 'SEVERIDAD_INVALIDA'], [$estado, $json['error']['codigo']], 'severidad');
    },
    'T-41 duplicado → 409 ALERGIA_DUPLICADA' => function () use ($llamar, $ALERGIAS) {
        [$estado, $json] = $llamar('POST', $ALERGIAS, 'tok-med-h01', ['alergeno_nombre' => 'IBUPROFENO', 'severidad' => 'severe']);
        Arnes::igual([409, 'ALERGIA_DUPLICADA'], [$estado, $json['error']['codigo']], 'respuesta');
    },
    'T-42 reconocer con huella vieja → 409 ALERTA_CAMBIO; con la vigente → 201 reconocida' => function () use ($llamar, $ALERGIAS, $penicilina) {
        $pdo = baseDePrueba();
        $vieja = $llamar('POST', $ALERGIAS, 'tok-med-h01', $penicilina, [], $pdo)[1]['huella'];
        $vigente = $llamar('POST', $ALERGIAS, 'tok-med-h01', ['alergeno_nombre' => 'Látex', 'severidad' => 'severe'], [], $pdo)[1]['huella'];
        $ruta = '/api/v1/pacientes/PAC-00451/alerta/reconocimientos';
        Arnes::igual(409, $llamar('POST', $ruta, 'tok-enf-h01', ['huella' => $vieja], [], $pdo)[0], 'huella vieja');
        [$estado, $json] = $llamar('POST', $ruta, 'tok-enf-h01', ['huella' => $vigente], [], $pdo);
        Arnes::igual([201, true], [$estado, $json['reconocida']], 'huella vigente');
    },
    'T-43 observabilidad: se respeta el X-Request-Id recibido y el registro no lleva datos clínicos' => function () use ($llamar, $ALERGIAS, $penicilina) {
        [, , $cab, $accesos] = $llamar('POST', $ALERGIAS, 'tok-med-h01', $penicilina, ['x-request-id' => 'prueba-0001-abc']);
        Arnes::igual('prueba-0001-abc', $cab['X-Request-Id'], 'request id');
        $linea = json_encode($accesos);
        Arnes::afirmar(!str_contains($linea, 'Penicilina') && !str_contains($linea, 'PAC-00451'), 'el registro filtra datos clínicos');
    },
    'T-44 base que rechaza la escritura → 503 PERSISTENCIA_NO_DISPONIBLE y 0 filas nuevas' => function () use ($llamar, $ALERGIAS, $penicilina) {
        $pdo = baseDePrueba();
        $pdo->prepare('SET default_transaction_read_only = on')->execute();
        [$estado, $json] = $llamar('POST', $ALERGIAS, 'tok-med-h01', $penicilina, [], $pdo);
        $pdo->prepare('SET default_transaction_read_only = off')->execute();
        Arnes::igual([503, 'PERSISTENCIA_NO_DISPONIBLE'], [$estado, $json['error']['codigo']], 'respuesta');
        Arnes::igual(1, contarAlergias($pdo), 'filas');
    },
];

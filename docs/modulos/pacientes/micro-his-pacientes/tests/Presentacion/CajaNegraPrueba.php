<?php

declare(strict_types=1);

/*
 * Semana 15 · Pruebas de CAJA NEGRA de Pacientes. Diseñadas desde el CONTRATO (api/openapi.yaml 1.2.0) y las
 * reglas R-01..R-11, sin mirar el código: solo usan la interfaz HTTP (Kernel::manejar) y cada caso parte
 * de un sistema nuevo. Técnicas: particiones de equivalencia, valores límite, tabla de decisión de
 * permisos, conjetura de errores y escenario de punta a punta.
 */

use Pacientes\Presentacion\Http\Respuesta;
use Pacientes\Presentacion\Http\Solicitud;
use Pacientes\Pruebas\Dobles\Escenario;

const CN_RUTA = '/api/v1/pacientes';
const CN_MOTIVO = 'Orden judicial de protección (víctima de violencia)';

$cnPedir = static fn (object $e, string $metodo, string $ruta, ?string $token = 'token-admision-oriente', ?array $cuerpo = null,
    array $consulta = [], array $cabeceras = [], string $hospital = Escenario::H_ORIENTE): Respuesta =>
    $e->kernel->manejar(Escenario::http($metodo, $ruta, $token, $cuerpo, $consulta, $cabeceras, $hospital));

/** [estado HTTP, código de error o null] */
$cnPar = static fn (Respuesta $r): array => [$r->estado, $r->cuerpo['error'] ?? null];

/** Cuerpo de alta a partir de los datos planos de Ana con cambios, más campos extra del JSON. */
$cnCuerpo = static fn (array $cambios = [], array $extra = []): array => array_merge(Escenario::json(Escenario::datosAna($cambios)), $extra);

/** Sistema nuevo con Ana (P-000001) registrada por admisión de hosp-oriente. */
$cnConAna = static function () use ($cnPedir, $cnCuerpo): object {
    $e = Escenario::crear();
    $r = $cnPedir($e, 'POST', CN_RUTA, 'token-admision-oriente', $cnCuerpo());
    if ($r->estado !== 201) {
        throw new RuntimeException('no se pudo preparar el escenario: ' . json_encode($r->cuerpo));
    }

    return $e;
};

/** Estado HTTP de un alta con esos cambios, en un sistema vacío. */
$cnAlta = static fn (array $cambios = [], array $extra = []): int => $cnPedir(Escenario::crear(), 'POST', CN_RUTA, 'token-admision-oriente', $cnCuerpo($cambios, $extra))->estado;

prueba('CN-01', 'Equivalencia · registro: 3 clases válidas (DPI, pasaporte, sin documento) y 10 inválidas, cada una con su código', function () use ($cnPedir, $cnPar, $cnCuerpo, $cnConAna): void {
    $alta = static fn (array $cuerpo, ?object $e = null): array => $cnPar($cnPedir($e ?? Escenario::crear(), 'POST', CN_RUTA, 'token-admision-oriente', $cuerpo));
    $sinDoc = ['tipo_documento' => null, 'numero_documento' => null];
    // Válidas
    igual([201, null], $alta($cnCuerpo()), 'V1 con DPI');
    igual([201, null], $alta($cnCuerpo(['tipo_documento' => 'PASAPORTE', 'numero_documento' => 'X1234567', 'pais_documento' => 'MEX'])), 'V2 con pasaporte');
    $provisional = $cnPedir(Escenario::crear(), 'POST', CN_RUTA, 'token-admision-oriente', $cnCuerpo($sinDoc + ['telefono' => null]));
    igual([201, true], [$provisional->estado, $provisional->cuerpo['identidad_provisional'] ?? null], 'V3 sin documento ni teléfono → identidad provisional');
    // Inválidas: datos (422)
    igual([422, 'DATOS_INVALIDOS'], $alta($cnCuerpo(['nombres' => ''])), 'I1 nombres vacíos');
    igual([422, 'DATOS_INVALIDOS'], $alta($cnCuerpo(['sexo' => 'mujer'])), 'I2 sexo fuera del catálogo');
    igual([422, 'DATOS_INVALIDOS'], $alta($cnCuerpo(['fecha_nacimiento' => '2030-01-01'])), 'I3 nacimiento futuro');
    igual([422, 'DATOS_INVALIDOS'], $alta($cnCuerpo(['fecha_nacimiento' => '1985-02-30'])), 'I4 fecha inexistente');
    igual([422, 'DATOS_INVALIDOS'], $alta($cnCuerpo(['tipo_documento' => 'CEDULA'])), 'I5 tipo de documento no admitido');
    // Inválidas: identidad (409)
    igual([409, 'PACIENTE_EXISTENTE'], $alta($cnCuerpo(['nombres' => 'Otra']), $cnConAna()), 'I6 DPI ya registrado');
    igual([409, 'POSIBLE_DUPLICADO'], $alta($cnCuerpo($sinDoc), $cnConAna()), 'I7 homónima con la misma fecha');
    // Inválidas: formato (400)
    igual([400, 'FORMATO_INVALIDO'], $alta(array_merge($cnCuerpo(), ['nombres' => 123])), 'I8 nombres no es texto');
    igual([400, 'FORMATO_INVALIDO'], $alta(array_merge($cnCuerpo(), ['identidad_protegida' => 'sí'])), 'I9 identidad_protegida no es booleano');
    igual([400, 'CUERPO_INVALIDO'], $cnPar($cnPedir(Escenario::crear(), 'POST', CN_RUTA)), 'I10 sin cuerpo');
});

prueba('CN-02', 'Equivalencia · búsqueda y edición: por código, documento y nombre; criterio insuficiente; cambio con y sin motivo; documento ajeno, ausente y null', function () use ($cnPedir, $cnPar, $cnCuerpo, $cnConAna): void {
    $e = $cnConAna();
    $cnPedir($e, 'POST', CN_RUTA, 'token-admision-oriente', Escenario::json(Escenario::datosLuis()));
    $total = static fn (array $q): array => [($r = $cnPedir($e, 'GET', CN_RUTA, consulta: $q))->estado, $r->cuerpo['total'] ?? $r->cuerpo['error']];
    igual([200, 1], $total(['codigo' => 'p-000001']), 'B1 código existente (minúsculas)');
    igual([200, 0], $total(['codigo' => 'P-000099']), 'B2 código inexistente');
    igual([200, 1], $total(['tipo_documento' => 'DPI', 'numero_documento' => '2547 89632 0101']), 'B3 documento con espacios');
    igual([200, 1], $total(['nombre' => 'LÓPEZ']), 'B4 nombre parcial con tilde y mayúsculas');
    igual([422, 'CRITERIO_INSUFICIENTE'], $total([]), 'B5 sin criterio');
    igual([422, 'DATOS_INVALIDOS'], $total(['tipo_documento' => 'DPI', 'numero_documento' => '2547']), 'B6 documento incompleto');

    $put = static fn (array $cuerpo, int $version): array => $cnPar($cnPedir($e, 'PUT', CN_RUTA . '/P-000001', cuerpo: $cuerpo, cabeceras: ['If-Match' => (string) $version]));
    igual([200, null], $put($cnCuerpo(['telefono' => '+502 4444 0000']), 1), 'E1 solo teléfono, sin motivo');
    igual([422, 'MOTIVO_REQUERIDO'], $put($cnCuerpo(['nombres' => 'Ana María']), 2), 'E2 identidad sin motivo');
    igual([200, null], $put($cnCuerpo(['nombres' => 'Ana María'], ['motivo' => 'Corrección según DPI']), 2), 'E3 identidad con motivo');
    igual([409, 'IDENTIFICADOR_EN_USO'], $put($cnCuerpo(['nombres' => 'Ana María', 'numero_documento' => Escenario::DPI_LUIS], ['motivo' => 'Corrección']), 3), 'E4 DPI de Luis');
    $sinCampo = $cnCuerpo(['nombres' => 'Ana María']);
    unset($sinCampo['documento']);
    igual([200, null], $put($sinCampo, 3), 'E5 sin el campo documento (B-4)');
    $detalle = $cnPedir($e, 'GET', CN_RUTA . '/P-000001')->cuerpo['paciente'];
    igual(['*********0101', 4], [$detalle['identificador'], $detalle['version']], 'E5 conserva el documento');
    igual([422, 'MOTIVO_REQUERIDO'], $put(array_merge($sinCampo, ['documento' => null]), 4), 'E6 documento null sin motivo');
    igual([200, null], $put(array_merge($sinCampo, ['documento' => null, 'motivo' => 'Documento mal asociado']), 4), 'E7 documento null con motivo');
    igual(true, $cnPedir($e, 'GET', CN_RUTA . '/P-000001')->cuerpo['paciente']['identidad_provisional'], 'E7 queda provisional');
});

prueba('CN-03', 'Valores límite · registro: nombres 100/101, teléfono 7/8/15/16, nacimiento hoy/mañana, pasaporte 5/6/20/21, DPI 12/13/14 y departamento 00/01/22/23', function () use ($cnAlta): void {
    igual([201, 422], [$cnAlta(['nombres' => str_repeat('ñ', 100)]), $cnAlta(['nombres' => str_repeat('ñ', 101)])], 'nombres de 100 / 101 caracteres');
    igual([201, 422], [$cnAlta(['apellidos' => str_repeat('A', 100)]), $cnAlta(['apellidos' => str_repeat('A', 101)])], 'apellidos de 100 / 101');
    igual([422, 201, 201, 422], [$cnAlta(['telefono' => '1234567']), $cnAlta(['telefono' => '12345678']),
        $cnAlta(['telefono' => str_repeat('5', 15)]), $cnAlta(['telefono' => str_repeat('5', 16)])], 'teléfono de 7 / 8 / 15 / 16 dígitos');
    igual([201, 422], [$cnAlta(['fecha_nacimiento' => '2026-10-21']), $cnAlta(['fecha_nacimiento' => '2026-10-22'])], 'nacimiento hoy / mañana (reloj 2026-10-21)');
    $pasaporte = static fn (string $n): array => ['tipo_documento' => 'PASAPORTE', 'numero_documento' => $n, 'pais_documento' => 'GTM'];
    igual([422, 201, 201, 422], [$cnAlta($pasaporte('A1234')), $cnAlta($pasaporte('A12345')), $cnAlta($pasaporte(str_repeat('B', 20))),
        $cnAlta($pasaporte(str_repeat('B', 21)))], 'pasaporte de 5 / 6 / 20 / 21');
    igual([422, 201, 422], [$cnAlta(['numero_documento' => '254789632010']), $cnAlta(['numero_documento' => '2547896320101']),
        $cnAlta(['numero_documento' => '25478963201011'])], 'DPI de 12 / 13 / 14 dígitos');
    igual([422, 201, 201, 422], [$cnAlta(['numero_documento' => '2547896320001']), $cnAlta(['numero_documento' => '2547896320101']),
        $cnAlta(['numero_documento' => '2547896322201']), $cnAlta(['numero_documento' => '2547896322301'])], 'departamento 00 / 01 / 22 / 23');
});

prueba('CN-04', 'Valores límite · búsqueda, versión, claves y ruta: 2/3 letras, 20/21 resultados, If-Match vigente ±1 y 9/10 dígitos, Idempotency-Key 7/8/64/65, código 20/21', function () use ($cnPedir, $cnPar, $cnCuerpo, $cnConAna): void {
    $e = Escenario::crear();
    for ($i = 1; $i <= 21; $i++) {
        $cnPedir($e, 'POST', CN_RUTA, cuerpo: Escenario::json(['nombres' => 'Pedro', 'apellidos' => "Gómez $i", 'fecha_nacimiento' => '1990-01-01', 'sexo' => 'male']));
    }
    $buscar = static fn (array $q): array => [($r = $cnPedir($e, 'GET', CN_RUTA, consulta: $q))->estado, $r->cuerpo['total'] ?? $r->cuerpo['error']];
    igual([[422, 'CRITERIO_INSUFICIENTE'], [200, 20]], [$buscar(['nombre' => 'go']), $buscar(['nombre' => 'gom'])], 'nombre de 2 / 3 letras');
    igual([200, 20], $buscar(['nombre' => 'gomez']), '21 coincidencias → 20 (límite de R-10)');
    igual([200, 11], $buscar(['nombre' => 'pedro gomez 1']), 'por debajo del límite, todas (1, 10..19)');
    igual([[200, 20], [200, 20], [200, 20], [422, 'DATOS_INVALIDOS']], [$buscar(['nombre' => 'gomez', 'nacimiento' => '1990']),
        $buscar(['nombre' => 'gomez', 'nacimiento' => '1990-01']), $buscar(['nombre' => 'gomez', 'nacimiento' => '1990-01-01']),
        $buscar(['nombre' => 'gomez', 'nacimiento' => '90'])], 'nacimiento AAAA / AAAA-MM / AAAA-MM-DD / AA');

    $e = $cnConAna();
    $put = static fn (string $ifMatch): array => $cnPar($cnPedir($e, 'PUT', CN_RUTA . '/P-000001', cuerpo: $cnCuerpo(['telefono' => '+502 4444 0000']),
        cabeceras: ['If-Match' => $ifMatch]));
    igual([[409, 'VERSION_DESACTUALIZADA'], [409, 'VERSION_DESACTUALIZADA'], [200, null]], [$put('0'), $put('2'), $put('1')], 'If-Match vigente-1 / vigente+1 / vigente');
    igual([[409, 'VERSION_DESACTUALIZADA'], [400, 'IF_MATCH_INVALIDO']], [$put('999999999'), $put('1000000000')], 'If-Match de 9 / 10 dígitos');

    $clave = static fn (string $k): int => $cnPedir(Escenario::crear(), 'POST', CN_RUTA, cuerpo: $cnCuerpo(), cabeceras: ['Idempotency-Key' => $k])->estado;
    igual([400, 201, 201, 400], [$clave(str_repeat('k', 7)), $clave(str_repeat('k', 8)), $clave(str_repeat('k', 64)), $clave(str_repeat('k', 65))],
        'Idempotency-Key de 7 / 8 / 64 / 65');
    igual([[404, 'PACIENTE_NO_ENCONTRADO'], [404, 'RUTA_NO_ENCONTRADA']], [$cnPar($cnPedir($e, 'GET', CN_RUTA . '/P-' . str_repeat('9', 18))),
        $cnPar($cnPedir($e, 'GET', CN_RUTA . '/P-' . str_repeat('9', 19)))], 'código de 20 / 21 caracteres');
});

prueba('CN-05', 'Permisos · tabla de decisión: 7 identidades × 6 operaciones; solo pasa quien tiene el permiso y cada rechazo no revela datos', function () use ($cnPedir, $cnCuerpo, $cnConAna): void {
    $operaciones = [
        'registrar' => fn (object $e, ?string $t, string $h) => $cnPedir($e, 'POST', CN_RUTA, $t, Escenario::json(Escenario::datosLuis()), hospital: $h),
        'buscar' => fn (object $e, ?string $t, string $h) => $cnPedir($e, 'GET', CN_RUTA, $t, consulta: ['nombre' => 'lopez'], hospital: $h),
        'leer' => fn (object $e, ?string $t, string $h) => $cnPedir($e, 'GET', CN_RUTA . '/P-000001', $t, hospital: $h),
        'editar' => fn (object $e, ?string $t, string $h) => $cnPedir($e, 'PUT', CN_RUTA . '/P-000001', $t, $cnCuerpo(['telefono' => '+502 4444 0000']),
            cabeceras: ['If-Match' => '1'], hospital: $h),
        'proteger' => fn (object $e, ?string $t, string $h) => $cnPedir($e, 'POST', CN_RUTA, $t,
            Escenario::json(Escenario::datosLuis(), ['identidad_protegida' => true, 'motivo' => CN_MOTIVO]), hospital: $h),
        'confirmar' => fn (object $e, ?string $t, string $h) => $cnPedir($e, 'POST', CN_RUTA, $t,
            $cnCuerpo(['tipo_documento' => null, 'numero_documento' => null], ['confirmar_coincidencia' => true, 'motivo' => 'Es otra persona']), hospital: $h),
    ];
    // identidad => [token, hospital, [registrar, buscar, leer, editar, proteger, confirmar]]; null = celda fuera de la tabla
    $tabla = [
        'admisión' => ['token-admision-oriente', Escenario::H_ORIENTE, [201, 200, 200, 200, 403, 403]],
        'clínico' => ['token-clinico-oriente', Escenario::H_ORIENTE, [403, 200, 200, 403, 403, 403]],
        'supervisión' => ['token-supervisor-oriente', Escenario::H_ORIENTE, [201, 200, 200, 200, 201, 201]],
        'admisión de otro hospital' => ['token-admision-norte', Escenario::H_NORTE, [201, 200, 404, 404, 403, null]], // confirmar: ver DEF-02
        'sin token' => [null, Escenario::H_ORIENTE, [401, 401, 401, 401, 401, 401]],
        'token inválido' => ['token-falso', Escenario::H_ORIENTE, [401, 401, 401, 401, 401, 401]],
        'token de oriente, cabecera de norte' => ['token-admision-oriente', Escenario::H_NORTE, [403, 403, 403, 403, 403, 403]],
    ];
    $celdas = 0;
    foreach ($tabla as $identidad => [$token, $hospital, $esperados]) {
        foreach (array_keys($operaciones) as $i => $operacion) {
            if ($esperados[$i] === null) {
                continue;
            }
            $e = $cnConAna();
            $r = $operaciones[$operacion]($e, $token, $hospital);
            igual($esperados[$i], $r->estado, "$identidad × $operacion");
            if ($r->estado >= 400) {
                igual(['error'], array_keys($r->cuerpo), "$identidad × $operacion: el rechazo solo trae el código");
                igual(1, count($e->repositorio->todos()), "$identidad × $operacion: el rechazo no registró a nadie");
            }
            $celdas++;
        }
    }
    igual(41, $celdas, 'celdas evaluadas');
    $norte = $cnPedir($cnConAna(), 'GET', CN_RUTA, 'token-admision-norte', consulta: ['nombre' => 'lopez'], hospital: Escenario::H_NORTE);
    igual(0, $norte->cuerpo['total'], 'otro hospital no encuentra a Ana por nombre');
});

prueba('CN-06', 'Errores (conjetura): 400 sin hospital, 404 ruta, 405 método, 428 sin If-Match, 400 If-Match mal formado, 404 de otro hospital idéntico a inexistente, 503 sin detalles', function () use ($cnPedir, $cnPar, $cnCuerpo, $cnConAna): void {
    $e = $cnConAna();
    $sinHospital = $e->kernel->manejar(new Solicitud('GET', CN_RUTA, ['Authorization' => 'Bearer token-admision-oriente'], ['nombre' => 'lopez'], null));
    igual([400, 'HOSPITAL_REQUERIDO'], $cnPar($sinHospital), 'sin X-Tenant-ID');
    igual([404, 'RUTA_NO_ENCONTRADA'], $cnPar($cnPedir($e, 'GET', '/api/v1/pacientes-todos')), 'ruta inexistente');
    igual([405, 'METODO_NO_PERMITIDO'], $cnPar($cnPedir($e, 'DELETE', CN_RUTA . '/P-000001')), 'DELETE no existe en el contrato');
    igual([428, 'VERSION_REQUERIDA'], $cnPar($cnPedir($e, 'PUT', CN_RUTA . '/P-000001', cuerpo: $cnCuerpo())), 'PUT sin If-Match');
    igual([400, 'IF_MATCH_INVALIDO'], $cnPar($cnPedir($e, 'PUT', CN_RUTA . '/P-000001', cuerpo: $cnCuerpo(), cabeceras: ['If-Match' => 'v1'])), 'If-Match «v1»');
    $ajeno = $cnPedir($e, 'GET', CN_RUTA . '/P-000001', 'token-admision-norte', hospital: Escenario::H_NORTE);
    $inexistente = $cnPedir($e, 'GET', CN_RUTA . '/P-000099', 'token-admision-norte', hospital: Escenario::H_NORTE);
    igual([$inexistente->estado, $inexistente->cuerpo], [$ajeno->estado, $ajeno->cuerpo], 'la ficha de otro hospital responde igual que una inexistente (RF-06)');
    $e->repositorio->fallarEn = ['buscar', 'registrar'];
    foreach ([$cnPedir($e, 'GET', CN_RUTA, consulta: ['nombre' => 'lopez']), $cnPedir($e, 'POST', CN_RUTA, cuerpo: Escenario::json(Escenario::datosLuis()))] as $caida) {
        igual([503, ['error' => 'SERVICIO_NO_DISPONIBLE']], [$caida->estado, $caida->cuerpo], 'base caída: sin mensaje interno');
    }
});

prueba('CN-07', 'Escenario de punta a punta · confidencialidad: proteger → buscar y leer reducido → 409 RESERVADO → editar sin verla → quitar la protección con motivo', function () use ($cnPedir, $cnPar, $cnCuerpo): void {
    $e = Escenario::crear();
    igual(201, $cnPedir($e, 'POST', CN_RUTA, 'token-supervisor-oriente', $cnCuerpo([], ['identidad_protegida' => true, 'motivo' => CN_MOTIVO]))->estado, '1 supervisión la registra protegida');
    $clinico = static fn (array $q): array => $cnPedir($e, 'GET', CN_RUTA, 'token-clinico-oriente', consulta: $q)->cuerpo;
    igual(0, $clinico(['nombre' => 'lopez'])['total'], '2 el clínico no la encuentra por nombre (RF-13)');
    $porCodigo = $clinico(['codigo' => 'P-000001'])['resultados'][0];
    igual(['Identidad protegida', null, null], [$porCodigo['nombre'], $porCodigo['nacimiento'], $porCodigo['identificador']],
        '3 por código, reducida');
    $detalle = $cnPedir($e, 'GET', CN_RUTA . '/P-000001', 'token-clinico-oriente')->cuerpo['paciente'];
    igual([null, null, null], [$detalle['telefono'], $detalle['nombres'], $detalle['apellidos']], '4 detalle reducido (RF-14, RF-19)');
    $duplicado = $cnPedir($e, 'POST', CN_RUTA, 'token-admision-oriente', $cnCuerpo(['nombres' => 'Otra']));
    igual([409, ['error' => 'PACIENTE_EXISTENTE', 'codigo' => 'RESERVADO']], [$duplicado->estado, $duplicado->cuerpo], '5 su DPI da 409 sin código ni documento (RF-16)');
    $editar = static fn (string $token, array $extra, string $version): array => $cnPar($cnPedir($e, 'PUT', CN_RUTA . '/P-000001', $token,
        $cnCuerpo(['telefono' => '+502 4444 0000'], $extra), cabeceras: ['If-Match' => $version]));
    igual([403, 'ACCESO_DENEGADO'], $editar('token-admision-oriente', [], '1'), '6 admisión no edita a quien no ve (RF-15)');
    igual([422, 'MOTIVO_REQUERIDO'], $editar('token-supervisor-oriente', ['identidad_protegida' => false], '1'), '7 quitar la protección sin motivo');
    igual([200, null], $editar('token-supervisor-oriente', ['identidad_protegida' => false, 'motivo' => 'Cesó la orden judicial'], '1'), '8 con motivo');
    igual(1, $clinico(['nombre' => 'lopez'])['total'], '9 ahora el clínico la encuentra por nombre');
    $evento = $e->bitacora->eventos('PACIENTE_ACTUALIZADO')[0];
    igual([true, false, 'Cesó la orden judicial'], [$evento->detalle['antes']['identidad_protegida'], $evento->detalle['despues']['identidad_protegida'],
        $evento->detalle['motivo']], '10 la auditoría registra el cambio y el motivo');
});

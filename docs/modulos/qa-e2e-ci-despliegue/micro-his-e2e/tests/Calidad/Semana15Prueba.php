<?php

declare(strict_types=1);

/*
 * Semana 15 — Pruebas por técnica. Escritas ANTES de corregir DEF-01 (RT-01 de la revisión técnica: reintentar el
 * registro de un despliegue lo duplicaba).
 *   UNI-*  unitarias del dominio (Despliegue::esReintento, sin dobles)
 *   BLA-*  caja blanca: cubren las DOS ramas nuevas de RegistrarDespliegue (reintento / despliegue nuevo)
 *   NEG-*  caja negra por HTTP: particiones de equivalencia y valores límite, sin mirar el código
 */

use E2e\Aplicacion\Resultados\Salida;
use E2e\Dominio\Modelo\Despliegue;

$hora = new DateTimeImmutable('2026-10-30T12:00:00Z');
$previo = static fn (string $estado = 'DESPLEGADO', string $guia = 'guia-2026.1', string $hace = '-2 minutes'): Despliegue
    => new Despliegue(7, 'hosp-norte', '2.4.0', Escenario::DIGEST, 'produccion', $guia, 'ops-luis', $estado, null, $hora->modify($hace));

prueba('UNI-01', 'Despliegue::esReintento: el mismo digest, entorno, guía y resultado a los 2 min es un reintento', function () use ($previo, $hora): void {
    igual(true, Despliegue::esReintento($previo(), Escenario::DIGEST, 'produccion', 'guia-2026.1', 'PASA', $hora), 'reintento');
});

prueba('UNI-02', 'Despliegue::esReintento: con otro resultado del smoke (FALLA frente a un DESPLEGADO) NO es reintento', function () use ($previo, $hora): void {
    igual(false, Despliegue::esReintento($previo(), Escenario::DIGEST, 'produccion', 'guia-2026.1', 'FALLA', $hora), 'otro smoke');
});

prueba('UNI-03', 'Despliegue::esReintento: sin despliegue previo, o fuera de la ventana de 15 min (límite: 15 min sí, 15 min 1 s no), NO es reintento', function () use ($previo, $hora): void {
    igual([false, true, false], [
        Despliegue::esReintento(null, Escenario::DIGEST, 'produccion', 'guia-2026.1', 'PASA', $hora),
        Despliegue::esReintento($previo(hace: '-15 minutes'), Escenario::DIGEST, 'produccion', 'guia-2026.1', 'PASA', $hora),
        Despliegue::esReintento($previo(hace: '-15 minutes -1 second'), Escenario::DIGEST, 'produccion', 'guia-2026.1', 'PASA', $hora),
    ], 'ventana');
});

// Caja blanca: RegistrarDespliegue tiene ahora dos caminos después de las reglas de R-11: «es reintento» y «es nuevo».
$aprobadaS15 = static function (): Escenario {
    $e = new Escenario();
    $id = $e->ejecucionEnCurso();
    $e->completar($id);
    $e->uc['gate']->ejecutar('resp-release', 'hosp-norte', '2.4.0', $id);

    return $e;
};
$desplegarS15 = static fn (Escenario $e, array $c = []): Salida
    => $e->uc['desplegar']->ejecutar('ops-luis', 'hosp-norte', '2.4.0', $c + ['digest' => Escenario::DIGEST, 'entorno' => 'produccion', 'guia' => 'guia-2026.1', 'smoke' => 'PASA']);

prueba('BLA-01', 'Rama «reintento» de RegistrarDespliegue: 200 DESPLIEGUE_YA_REGISTRADO con el MISMO id, sin registro ni traza nuevos', function () use ($aprobadaS15, $desplegarS15): void {
    $e = $aprobadaS15();
    $primero = $desplegarS15($e);
    $traza = count($e->auditoriaReal()->acciones());
    $otra = $desplegarS15($e);
    igual([Salida::OK, 'DESPLIEGUE_YA_REGISTRADO', $primero->datos['id']], [$otra->tipo, $otra->codigo, $otra->datos['id']], 'reintento');
    igual([1, $traza], [count($e->uc['trazabilidad']->ejecutar('dev-ana', 'hosp-norte', '2.4.0')->datos['despliegues']), count($e->auditoriaReal()->acciones())], 'sin efectos');
});

prueba('BLA-02', 'Rama «nuevo» de RegistrarDespliegue: con otra guía NO es reintento → 201 y un segundo registro', function () use ($aprobadaS15, $desplegarS15): void {
    $e = $aprobadaS15();
    $desplegarS15($e);
    $otro = $desplegarS15($e, ['guia' => 'guia-2026.2']);
    igual([Salida::CREADO, 2], [$otro->tipo, count($e->uc['trazabilidad']->ejecutar('dev-ana', 'hosp-norte', '2.4.0')->datos['despliegues'])], 'nuevo');
});

// Caja negra por HTTP (la especificación es el contrato 1.2.0, no el código).
$httpS15 = static function (Escenario $e, string $metodo, string $ruta, string $token, ?array $cuerpo = null, array $extra = []): object {
    $r = $e->kernel()->manejar(new E2e\Presentacion\Http\Solicitud($metodo, $ruta, ['authorization' => 'Bearer ' . $token, 'x-tenant-id' => 'hosp-norte'] + $extra,
        $cuerpo === null ? '' : json_encode($cuerpo)));

    return (object) ['estado' => $r->estado, 'cuerpo' => $r->cuerpo];
};

prueba('NEG-01', 'Caja negra (DEF-01): el mismo POST de despliegue dos veces → 201 y 200, y la trazabilidad tiene UN despliegue', function () use ($aprobadaS15, $httpS15): void {
    $e = $aprobadaS15();
    $c = ['digest' => Escenario::DIGEST, 'entorno' => 'produccion', 'guia' => 'guia-2026.1', 'smoke' => 'PASA'];
    $a = $httpS15($e, 'POST', '/api/v1/e2e/versiones/2.4.0/despliegues', 'token-ops', $c);
    $b = $httpS15($e, 'POST', '/api/v1/e2e/versiones/2.4.0/despliegues', 'token-ops', $c);
    $t = $httpS15($e, 'GET', '/api/v1/e2e/versiones/2.4.0/trazabilidad', 'token-ops');
    igual([201, 200, 1], [$a->estado, $b->estado, count($t->cuerpo['despliegues'])], 'reintento por HTTP');
});

prueba('NEG-02', 'Caja negra, partición inválida: guía fuera del formato guia-AAAA.N («guia-26.3», «GUIA-2026.3») → 422 DATOS_INVALIDOS', function () use ($aprobadaS15, $httpS15): void {
    $e = $aprobadaS15();
    $r = array_map(static fn (string $g): array => [($x = $httpS15($e, 'POST', '/api/v1/e2e/versiones/2.4.0/despliegues', 'token-ops',
        ['digest' => Escenario::DIGEST, 'entorno' => 'produccion', 'guia' => $g, 'smoke' => 'PASA']))->estado, $x->cuerpo['error'] ?? ''], ['guia-26.3', 'GUIA-2026.3']);
    igual([[422, 'DATOS_INVALIDOS'], [422, 'DATOS_INVALIDOS']], $r, 'guía');
});

prueba('NEG-03', 'Caja negra, valores límite de «intentos» (0..5 en el contrato): 5 → 201; 6 y -1 → rechazo', function () use ($httpS15): void {
    $e = new Escenario();
    $id = $e->ejecucionEnCurso();
    $rep = static fn (string $esc, int $n, string $k): int => $httpS15($e, 'POST', "/api/v1/e2e/ejecuciones/$id/resultados", 'token-pipeline',
        ['escenario' => $esc, 'resultado' => 'PASA', 'intentos' => $n], ['idempotency-key' => $k])->estado;
    $r = [$rep('E2E-101', 5, 'neg03-cinco'), $rep('E2E-102', 6, 'neg03-seis'), $rep('E2E-103', -1, 'neg03-menos')];
    igual([201, true, true], [$r[0], $r[1] >= 400, $r[2] >= 400], 'límites');
});

prueba('NEG-04', 'Caja negra, partición por rol: la vista previa del gate es 200 para Release y 403 para el pipeline y para Operaciones', function () use ($httpS15): void {
    $e = new Escenario();
    $id = $e->ejecucionEnCurso();
    $e->completar($id);
    $r = array_map(static fn (string $t): int => $httpS15($e, 'POST', '/api/v1/e2e/versiones/2.4.0/gate/vista-previa', $t, ['ejecucion' => $id])->estado,
        ['token-release', 'token-pipeline', 'token-ops']);
    igual([200, 403, 403], $r, 'roles');
});

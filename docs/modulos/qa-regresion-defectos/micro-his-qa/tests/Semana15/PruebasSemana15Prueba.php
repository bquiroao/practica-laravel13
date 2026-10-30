<?php

declare(strict_types=1);

/*
 * Semana 15 — Pruebas unitarias (UNI), de caja blanca (CB) y de caja negra (CN), trazadas a RF/RNF.
 * Escritas ANTES de corregir los hallazgos de la revisión de la semana 13 (RT-01, RT-02, RT-05, RT-06): en rojo
 * reproducen el defecto; en verde lo fijan. Solo prueban este módulo: el HIS central y el expediente no se tocan.
 */

use Qa\Dominio\Excepciones\ReglaRechazada;
use Qa\Dominio\Modelo\Campana;
use Qa\Dominio\Modelo\Defecto;
use Qa\Dominio\Modelo\EstadoCampana;
use Qa\Dominio\Modelo\EstadoDefecto;
use Qa\Dominio\Modelo\Intento;
use Qa\Dominio\Modelo\Resultado;
use Qa\Dominio\Modelo\Severidad;
use Qa\Presentacion\Http\Solicitud;

$s15Defecto = static fn (Severidad $propuesta, ?Severidad $clasificada = null, EstadoDefecto $estado = EstadoDefecto::NUEVO, string $caso = 'TC-107'): Defecto
    => new Defecto(1, 'hosp-norte', 1, $caso, 'Defecto', ['paso'], $propuesta, true, 'tito-tester', $estado, $clasificada, $clasificada === null ? null : 'ALTA');

/** Campaña EN_CURSO con un intento por caso: ['TC-107' => Resultado::PASA, …]. */
$s15Campana = static function (array $ultimos, float $umbral = 0.95): Campana {
    $c = new Campana(1, 'hosp-norte', 'Regresión', ['expediente'], array_fill_keys(array_keys($ultimos), 1), $umbral, 'lider-qa',
        EstadoCampana::EN_CURSO, '1.4.0+abc1234');
    foreach ($ultimos as $caso => $resultado) {
        if ($resultado !== null) {
            $c->agregar(new Intento(null, 1, $caso, 1, $resultado, 'tito-tester', [], [], null, null,
                $resultado === Resultado::NO_APLICA ? 'Sin ambiente' : null, "k-$caso", 'h', new DateTimeImmutable('2026-10-29')));
        }
    }

    return $c;
};

$s15Pedir = static function (Escenario $e, string $metodo, string $ruta, string $token = 'token-lider-qa', ?array $cuerpo = null, array $cabeceras = []): \Qa\Presentacion\Http\Respuesta {
    return $e->kernel()->manejar(new Solicitud($metodo, $ruta, $cabeceras + ['authorization' => "Bearer $token", 'x-tenant-id' => 'hosp-norte'],
        $cuerpo === null ? '' : json_encode($cuerpo)));
};

// ===== Unitarias: reglas del dominio ==================================================================================

prueba('UNI-01', 'RF-10 · R-09 (RT-01): un defecto sin clasificar cuenta con su severidad PROPUESTA; CRÍTICA propuesta = crítico abierto hasta que el Líder QA lo reclasifique', function () use ($s15Defecto): void {
    $d = $s15Defecto(Severidad::CRITICA);
    igual([Severidad::CRITICA, true], [$d->severidadEfectiva(), $d->criticoAbierto()], 'propuesta CRÍTICA sin clasificar');
    $d->clasificar(Severidad::MENOR, 'BAJA', 'lider-qa', 'Solo afecta un texto de ayuda');
    igual([Severidad::MENOR, false], [$d->severidadEfectiva(), $d->criticoAbierto()], 'reclasificado MENOR');
});

prueba('UNI-02', 'RF-10 · R-09 (RT-05): sin ningún caso aplicable (todo NO_APLICA) la campaña cierra NO_GO con su propia causa, nunca GO', function () use ($s15Campana): void {
    $d = $s15Campana(['TC-107' => Resultado::NO_APLICA, 'TC-108' => Resultado::NO_APLICA])->cerrar(0, 0);
    igual(['NO_GO', 0.0, ['ningún caso aplicable: no hay ejecución que respalde la liberación']], [$d->valor, $d->tasa, $d->causas], 'decisión');
});

prueba('UNI-03', 'RF-09 · R-08 (RT-06): marcar CORREGIDO exige un build x.y.z(+commit), el mismo formato que iniciar una campaña', function () use ($s15Defecto): void {
    $d = $s15Defecto(Severidad::MAYOR, Severidad::MAYOR, EstadoDefecto::ABIERTO);
    foreach (['ya quedó', '1.4', '1.4.1+XYZ', ' '] as $build) {
        $e = lanza(ReglaRechazada::class, fn () => $d->marcarCorregido($build, 'dev-luis'), "build «{$build}»");
        igual(['DATOS_INVALIDOS', ['build']], [$e->codigo, $e->detalle], "build «{$build}»");
    }
    $d->marcarCorregido('1.4.1+def5678', 'dev-luis');
    igual(EstadoDefecto::CORREGIDO, $d->estado(), 'build válido');
});

prueba('UNI-04', 'RF-10 · R-15: un MAYOR propuesto y sin clasificar bloquea el GO si su caso es crítico en la matriz; en un caso no crítico, no', function () use ($s15Defecto): void {
    igual(true, $s15Defecto(Severidad::MAYOR)->bloqueaGo(['TC-107']), 'TC-107 crítico');
    igual(false, $s15Defecto(Severidad::MAYOR, caso: 'TC-109')->bloqueaGo(['TC-107']), 'TC-109 no crítico');
});

// ===== Caja blanca: ramas y condiciones ===============================================================================

prueba('CB-01', 'RF-10 · R-09/R-15 · Cobertura de ramas de Campana::cerrar: cada una de las 7 decisiones se toma por verdadero y por falso', function () use ($s15Campana): void {
    $P = Resultado::PASA;
    $F = Resultado::FALLA;
    $N = Resultado::NO_APLICA;
    // [ultimos, umbral, críticos, mayores, esperado, causas, ramas verdaderas]
    $filas = [
        'r1 cerrada'        => [null, 0.95, 0, 0, 'ESTADO_NO_PERMITE_CERRAR', 0, 'b1'],
        'r2 pendiente'      => [['TC-107' => $P, 'TC-108' => null], 0.95, 0, 0, 'CASOS_PENDIENTES', 0, 'b2'],
        'r3 todo pasa'      => [['TC-107' => $P, 'TC-108' => $P], 0.95, 0, 0, 'GO', 0, 'b7'],
        'r4 nada aplica'    => [['TC-107' => $N, 'TC-108' => $N], 0.95, 0, 0, 'NO_GO', 1, 'b3'],
        'r5 crítico'        => [['TC-107' => $P, 'TC-108' => $P], 0.95, 1, 0, 'NO_GO', 1, 'b4'],
        'r6 mayor'          => [['TC-107' => $P, 'TC-108' => $P], 0.95, 0, 1, 'NO_GO', 1, 'b5'],
        'r7 bajo umbral'    => [['TC-107' => $P, 'TC-108' => $F], 0.95, 0, 0, 'NO_GO', 1, 'b6'],
        'r8 las tres'       => [['TC-107' => $P, 'TC-108' => $F], 0.95, 2, 1, 'NO_GO', 3, 'b4 b5 b6'],
        'r9 mixto aplica'   => [['TC-107' => $P, 'TC-108' => $N], 0.95, 0, 0, 'GO', 0, 'b7'],
    ];
    foreach ($filas as $nombre => [$ultimos, $umbral, $criticos, $mayores, $esperado, $causas]) {
        if ($ultimos === null) {
            $c = $s15Campana(['TC-107' => $P]);
            $c->cerrar(0, 0);
            igual($esperado, lanza(ReglaRechazada::class, fn () => $c->cerrar(0, 0), $nombre)->codigo, $nombre);
            continue;
        }
        $c = $s15Campana($ultimos, $umbral);
        if (!in_array($esperado, ['GO', 'NO_GO'], true)) {
            igual($esperado, lanza(ReglaRechazada::class, fn () => $c->cerrar($criticos, $mayores), $nombre)->codigo, $nombre);
            continue;
        }
        $d = $c->cerrar($criticos, $mayores);
        igual([$esperado, $causas], [$d->valor, count($d->causas)], $nombre);
    }
});

prueba('CB-02', 'RF-10 · R-09/R-15 · MC/DC de Defecto::bloqueaGo = (CRÍTICA ∧ no cerrado) ∨ (MAYOR ∧ no cerrado ∧ caso crítico), con la severidad efectiva', function () use ($s15Defecto): void {
    $C = Severidad::CRITICA;
    $M = Severidad::MAYOR;
    $m = Severidad::MENOR;
    $A = EstadoDefecto::ABIERTO;
    $X = EstadoDefecto::CERRADO;
    // [propuesta, clasificada, estado, caso, esperado]
    $filas = [
        'a CRÍTICA propuesta, nuevo'            => [$C, null, EstadoDefecto::NUEVO, 'TC-109', true],
        'c CRÍTICA, abierto, caso no crítico'   => [$C, $C, $A, 'TC-109', true],
        'b CRÍTICA, cerrado  (B cambia vs c)'   => [$C, $C, $X, 'TC-109', false],
        'd MENOR, abierto    (A cambia vs c)'   => [$C, $m, $A, 'TC-109', false],
        'e MAYOR, abierto, caso crítico'        => [$M, $M, $A, 'TC-107', true],
        'f MAYOR, caso no crítico (D vs e)'     => [$M, $M, $A, 'TC-109', false],
        'g MAYOR, cerrado    (B vs e)'          => [$M, $M, $X, 'TC-107', false],
        'h MENOR, caso crítico (C vs e)'        => [$M, $m, $A, 'TC-107', false],
        'i MAYOR propuesta, nuevo, crítico'     => [$M, null, EstadoDefecto::NUEVO, 'TC-107', true],
        'j CORREGIDO todavía bloquea'           => [$C, $C, EstadoDefecto::CORREGIDO, 'TC-109', true],
    ];
    foreach ($filas as $nombre => [$propuesta, $clasificada, $estado, $caso, $esperado]) {
        igual($esperado, $s15Defecto($propuesta, $clasificada, $estado, $caso)->bloqueaGo(['TC-107', 'TC-108']), $nombre);
    }
});

prueba('CB-03', 'RF-11 · D-23 (RT-02) · Caminos de la trazabilidad: defectos de esta campaña, abiertos de otra (se muestran) y cerrados de otra (no); sin duplicados', function (): void {
    $e = new Escenario();
    $a = $e->campanaEnCurso(['TC-107', 'TC-108']);
    $e->registrar($a, 'TC-107', 'FALLA');                                   // defecto 1, campaña A, queda abierto
    $e->registrar($a, 'TC-108', 'FALLA');                                   // defecto 2, campaña A, se cerrará
    $e->uc['clasificar']->ejecutar('lider-qa', 'hosp-norte', 1, ['severidad' => 'CRITICA', 'prioridad' => 'ALTA', 'motivo' => 'Nota firmada editable']);
    $e->uc['clasificar']->ejecutar('lider-qa', 'hosp-norte', 2, ['severidad' => 'MAYOR', 'prioridad' => 'ALTA']);
    $e->uc['corregir']->ejecutar('dev-luis', 'hosp-norte', 2, '1.4.1+def5678');
    $e->registrar($a, 'TC-108', 'PASA', ['verifica_defecto' => 2]);
    $e->uc['cerrar']->ejecutar('lider-qa', 'hosp-norte', $a);
    $b = $e->campanaEnCurso(['TC-107', 'TC-108']);
    $e->registrar($b, 'TC-107', 'FALLA');                                   // defecto 3, campaña B
    $t = $e->uc['trazabilidad']->ejecutar('lider-qa', 'hosp-norte', $b)->datos['casos'];
    $ids = static fn (array $fila): array => array_map(static fn (array $d): array => [$d['id'], $d['campana'], $d['bloquea_go']], $fila['defectos']);
    igual([[1, $a, true], [3, $b, true]], $ids($t[0]), 'TC-107: el abierto de A y el de B, una vez cada uno');
    igual([], $ids($t[1]), 'TC-108: el de A está CERRADO y no se muestra');
});

// ===== Caja negra: equivalencia, límites, permisos y errores, por HTTP ================================================

prueba('CN-01', 'RF-04 · RF-05 · Clases de equivalencia del resultado: PASA y FALLA válidas 201; FALLA sin evidencia, NO_APLICA sin motivo y valor fuera del enum 422 (regla); tipo equivocado 400 (forma, D-13)', function () use ($s15Pedir): void {
    $e = new Escenario();
    $id = $e->campanaEnCurso(['TC-107', 'TC-108', 'TC-109']);
    $clave = 0;
    $r = static function (array $cuerpo) use ($s15Pedir, $e, $id, &$clave): array {
        $x = $s15Pedir($e, 'POST', "/api/v1/qa/campanas/$id/resultados", 'token-tito-tester', $cuerpo, ['idempotency-key' => sprintf('cn01-%08d', ++$clave)]);

        return [$x->estado, $x->cuerpo['codigo'] ?? $x->cuerpo['error'] ?? null];
    };
    $foto = [['tipo' => 'captura', 'contenido_base64' => base64_encode(Escenario::PNG)]];
    igual([201, 'RESULTADO_REGISTRADO'], $r(['caso' => 'TC-107', 'resultado' => 'PASA']), 'PASA válida');
    igual([201, 'RESULTADO_REGISTRADO'], $r(['caso' => 'TC-108', 'resultado' => 'FALLA', 'evidencias' => $foto,
        'defecto' => ['titulo' => 'Falla', 'pasos' => ['Paso'], 'severidad' => 'MENOR']]), 'FALLA válida');
    igual([422, 'EVIDENCIA_REQUERIDA'], $r(['caso' => 'TC-109', 'resultado' => 'FALLA', 'defecto' => ['titulo' => 'x', 'pasos' => ['y'], 'severidad' => 'MENOR']]), 'FALLA sin evidencia');
    igual([422, 'MOTIVO_REQUERIDO'], $r(['caso' => 'TC-109', 'resultado' => 'NO_APLICA']), 'NO_APLICA sin motivo');
    igual([422, 'DATOS_INVALIDOS'], $r(['caso' => 'TC-109', 'resultado' => 'TAL_VEZ']), 'resultado fuera del enum: lo rechaza el dominio');
    igual(400, $r(['caso' => 'TC-109', 'resultado' => 7])[0], 'resultado que no es texto: lo rechaza el formulario');
});

prueba('CN-02', 'RF-10 · RF-05 · Valores límite: tasa justo en el umbral → GO y un punto por debajo → NO_GO; evidencia de 5 MB exactos → 201 y de 5 MB + 1 byte → 413', function () use ($s15Pedir): void {
    foreach ([[0.5, 'GO'], [0.5001, 'NO_GO']] as [$umbral, $esperado]) {
        $e = new Escenario();
        $id = $e->campanaEnCurso(['TC-104', 'TC-105'], ['sincronizacion'], $umbral);
        $e->registrar($id, 'TC-104', 'PASA');
        $e->registrar($id, 'TC-105', 'FALLA');                              // MAYOR en un caso NO crítico: no bloquea
        $c = $s15Pedir($e, 'POST', "/api/v1/qa/campanas/$id/cierre");
        igual([200, $esperado], [$c->estado, $c->cuerpo['decision']], "umbral $umbral con tasa 0,5");
    }
    $e = new Escenario();
    $id = $e->campanaEnCurso(['TC-107', 'TC-108']);
    $con = static fn (int $bytes, string $clave): int => $s15Pedir($e, 'POST', "/api/v1/qa/campanas/$id/resultados", 'token-tito-tester', ['caso' => 'TC-107',
        'resultado' => 'FALLA', 'evidencias' => [['tipo' => 'captura', 'contenido_base64' => base64_encode(str_pad(Escenario::PNG, $bytes, 'x'))]],
        'defecto' => ['titulo' => 'Límite', 'pasos' => ['Paso'], 'severidad' => 'MENOR']], ['idempotency-key' => $clave])->estado;
    igual([413, 201], [$con(5 * 1024 * 1024 + 1, 'cn02-mas-uno'), $con(5 * 1024 * 1024, 'cn02-exacto')], '5 MB + 1 y 5 MB exactos');
});

prueba('CN-03', 'RF-14 · R-13 · Permisos por rol y tenant: cada rol solo hace lo suyo (403), el producto solo consulta, y otro hospital no ve la campaña', function () use ($s15Pedir): void {
    $e = new Escenario();
    $id = $e->campanaEnCurso(['TC-107', 'TC-108']);
    $e->registrar($id, 'TC-107', 'FALLA');
    $e->registrar($id, 'TC-108', 'PASA');
    $casos = [
        'tester cierra'             => ['POST', "/api/v1/qa/campanas/$id/cierre", 'token-tito-tester', null, 403],
        'desarrollador clasifica'   => ['POST', '/api/v1/qa/defectos/1/clasificacion', 'token-dev-luis', ['severidad' => 'MENOR', 'prioridad' => 'BAJA', 'motivo' => 'x'], 403],
        'analista aprueba'          => ['POST', '/api/v1/qa/casos/TC-107/aprobacion', 'token-ana-qa', null, 403],
        'producto cierra'           => ['POST', "/api/v1/qa/campanas/$id/cierre", 'token-producto', null, 403],
        'producto consulta'         => ['GET', "/api/v1/qa/campanas/$id/trazabilidad", 'token-producto', null, 200],
        'otro hospital (su token)'  => ['GET', "/api/v1/qa/campanas/$id/trazabilidad", 'token-tester-sur', null, 403],
    ];
    foreach ($casos as $nombre => [$metodo, $ruta, $token, $cuerpo, $estado]) {
        igual($estado, $s15Pedir($e, $metodo, $ruta, $token, $cuerpo)->estado, $nombre);
    }
    $sur = $e->kernel()->manejar(new Solicitud('GET', "/api/v1/qa/campanas/$id/trazabilidad", ['authorization' => 'Bearer token-tester-sur', 'x-tenant-id' => 'hosp-sur']));
    igual(404, $sur->estado, 'otro hospital en su propio tenant: la campaña no existe para él');
    igual(200, $s15Pedir($e, 'POST', "/api/v1/qa/campanas/$id/cierre")->estado, 'el Líder QA sí cierra');
});

prueba('CN-04', 'RF-04 · RF-06 · RNF-03 · Errores: campaña inexistente 404, cuerpo que no es JSON 400, campaña cerrada 409, almacén caído 503 sin escribir nada', function () use ($s15Pedir): void {
    $e = new Escenario();
    igual(404, $s15Pedir($e, 'GET', '/api/v1/qa/campanas/999/trazabilidad')->estado, 'inexistente');
    $malformado = $e->kernel()->manejar(new Solicitud('POST', '/api/v1/qa/campanas', ['authorization' => 'Bearer token-lider-qa', 'x-tenant-id' => 'hosp-norte'], '{"nombre": '));
    igual(400, $malformado->estado, 'JSON malformado');
    $id = $e->campanaEnCurso(['TC-107', 'TC-108']);
    $e->almacen->caido = true;
    $caido = $s15Pedir($e, 'POST', "/api/v1/qa/campanas/$id/resultados", 'token-tito-tester', ['caso' => 'TC-107', 'resultado' => 'FALLA',
        'evidencias' => [['tipo' => 'captura', 'contenido_base64' => base64_encode(Escenario::PNG)]], 'defecto' => ['titulo' => 'x', 'pasos' => ['y'], 'severidad' => 'MENOR']],
        ['idempotency-key' => 'cn04-caido-01']);
    igual([503, 0], [$caido->estado, count($e->intentos())], 'almacén caído: 503 y ningún intento');
    $e->almacen->caido = false;
    $e->registrar($id, 'TC-107', 'PASA');
    $e->registrar($id, 'TC-108', 'PASA');
    $s15Pedir($e, 'POST', "/api/v1/qa/campanas/$id/cierre");
    igual(409, $s15Pedir($e, 'POST', "/api/v1/qa/campanas/$id/resultados", 'token-tito-tester', ['caso' => 'TC-107', 'resultado' => 'PASA'],
        ['idempotency-key' => 'cn04-cerrada-1'])->estado, 'campaña cerrada');
});

prueba('CN-05', 'RF-10 · RF-11 · D-23 (RT-01, RT-02): lo que la trazabilidad marca como bloqueante es exactamente lo que el cierre cuenta', function () use ($s15Pedir): void {
    $e = new Escenario();
    $id = $e->campanaEnCurso(['TC-107', 'TC-108', 'TC-109'], ['expediente'], 0.6);
    $s15Pedir($e, 'POST', "/api/v1/qa/campanas/$id/resultados", 'token-tito-tester', ['caso' => 'TC-109', 'resultado' => 'FALLA',
        'evidencias' => [['tipo' => 'captura', 'contenido_base64' => base64_encode(Escenario::PNG)]],
        'defecto' => ['titulo' => 'Rango imposible aceptado', 'pasos' => ['Registrar 999 lpm'], 'severidad' => 'CRITICA']], ['idempotency-key' => 'cn05-falla-01']);
    $e->registrar($id, 'TC-107', 'PASA');
    $e->registrar($id, 'TC-108', 'PASA');
    $t = $s15Pedir($e, 'GET', "/api/v1/qa/campanas/$id/trazabilidad")->cuerpo;
    $bloquean = array_merge(...array_map(static fn (array $f): array => array_values(array_filter($f['defectos'], static fn (array $d): bool => $d['bloquea_go'])), $t['casos']));
    $c = $s15Pedir($e, 'POST', "/api/v1/qa/campanas/$id/cierre")->cuerpo;
    igual([1, false, 'NO_GO', ['1 defecto crítico abierto']], [count($bloquean), $bloquean[0]['clasificado'] ?? null, $c['decision'], $c['causas']],
        'CRÍTICA propuesta sin clasificar: la trazabilidad la marca y el cierre la cuenta');
});

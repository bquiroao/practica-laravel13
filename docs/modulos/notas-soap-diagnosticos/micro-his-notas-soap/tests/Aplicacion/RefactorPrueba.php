<?php

declare(strict_types=1);

/*
 * Semana 7 — pruebas de CARACTERIZACIÓN del refactor.
 * Fijan el comportamiento que el refactor iba a mover de lugar (formato de la bitácora, orden de las
 * verificaciones y manejo de fallas en los dos casos de uso que escriben notas). Se escribieron y se
 * ejecutaron sobre el código ANTERIOR al refactor, y tienen que seguir pasando después.
 */

use NotasSoap\Dominio\Modelo\NotaSoap;
use NotasSoap\Dominio\Modelo\SeccionesSoap;
use NotasSoap\Pruebas\Dobles\Escenario;
use NotasSoap\Pruebas\Dobles\RepositorioDiagnosticosQueFalla;
use NotasSoap\Pruebas\Dobles\VerificadorAccesoQueExplota;

/** Nota firmada guardada directo en el almacén (para preparar combinaciones que el flujo normal no produce). */
$firmadaDirecta = static function (Escenario $e, string $id, string $expediente, string $episodio): NotaSoap {
    $nota = NotaSoap::registrar($id, $expediente, $episodio, 'dra-mendez',
        new SeccionesSoap('Dolor abdominal', 'Abdomen blando', 'Gastritis', 'Omeprazol'), new DateTimeImmutable('2026-10-01T08:00:00-06:00'))
        ->firmar(new DateTimeImmutable('2026-10-01T08:10:00-06:00'));
    $e->notas->guardar($nota);

    return $nota;
};
$codigo = static fn ($r): array => [$r->estado, $r->cuerpo['error']['code'] ?? null];

prueba('REF-01', 'Caracterización: nombre, campos y orden exactos de los eventos de bitácora al registrar, firmar y corregir', function (): void {
    $e = new Escenario();
    $k = $e->kernel;
    $v1 = $k->manejar(Escenario::peticion())->cuerpo['clinical_note_id'];
    $k->manejar(Escenario::peticion([], [], 'POST', "/api/v1/notas-soap/$v1/firma"));
    $v2 = $k->manejar(Escenario::peticionCorreccion($v1))->cuerpo['clinical_note_id'];
    $k->manejar(Escenario::peticion([], [], 'POST', "/api/v1/notas-soap/$v2/firma"));
    igual([
        ['evento' => 'NOTA_REGISTRADA', 'datos' => ['nota_id' => $v1, 'autor_id' => 'dra-mendez']],
        ['evento' => 'NOTA_FIRMADA', 'datos' => ['nota_id' => $v1, 'autor_id' => 'dra-mendez']],
        ['evento' => 'NOTA_CORREGIDA', 'datos' => ['nota_id' => $v2, 'reemplaza_a' => $v1, 'autor_id' => 'dra-mendez']],
        ['evento' => 'NOTA_FIRMADA', 'datos' => ['nota_id' => $v2, 'autor_id' => 'dra-mendez']],
    ], $e->auditoria->eventos(), 'bitácora');
});

prueba('REF-02', 'Caracterización: orden de las verificaciones (hospital → acceso → versión ya corregida → expediente → reglas)', function () use ($firmadaDirecta, $codigo): void {
    $e = new Escenario();
    $k = $e->kernel;
    // Registro: el acceso se revisa antes que el expediente.
    igual([403, 'acceso_denegado'], $codigo($k->manejar(Escenario::peticion(['medical_record_id' => 'exp-rosa', 'care_episode_id' => 'epi-200'], ['authorization' => 'Bearer token-lopez']))), 'registro: sin asignación y expediente cerrado');
    igual([403, 'acceso_denegado'], $codigo($k->manejar(Escenario::peticion(['medical_record_id' => 'exp-rosa', 'plan' => ''], ['x-tenant-id' => 'hosp-central']))), 'registro: otro hospital, expediente cerrado y sección vacía');
    igual([409, 'expediente_no_disponible'], $codigo($k->manejar(Escenario::peticion(['medical_record_id' => 'exp-rosa', 'care_episode_id' => 'epi-200', 'plan' => '']))), 'registro: expediente cerrado antes que sección vacía');
    // Corrección: el hospital antes que la existencia; el acceso antes que «ya corregida»; «ya corregida» antes que el expediente.
    igual([403, 'acceso_denegado'], $codigo($k->manejar(Escenario::peticionCorreccion('no-existe', [], ['x-tenant-id' => 'hosp-central']))), 'corrección: otro hospital y nota inexistente');
    $rosa = $firmadaDirecta($e, 'nota-rosa', 'exp-rosa', 'epi-200');
    $e->notas->guardar(NotaSoap::corregir($rosa, 'nota-rosa-v2', $rosa->secciones, 'Corrección hecha antes de cerrar el expediente', new DateTimeImmutable('2026-10-01T09:00:00-06:00')));
    igual([403, 'acceso_denegado'], $codigo($k->manejar(Escenario::peticionCorreccion('nota-rosa', [], ['authorization' => 'Bearer token-lopez']))), 'corrección: no es el autor y ya corregida');
    igual([409, 'nota_ya_corregida'], $codigo($k->manejar(Escenario::peticionCorreccion('nota-rosa'))), 'corrección: ya corregida y expediente cerrado');
    $firmadaDirecta($e, 'nota-rosa-b', 'exp-rosa', 'epi-200');
    igual([409, 'expediente_no_disponible'], $codigo($k->manejar(Escenario::peticionCorreccion('nota-rosa-b', ['correction_reason' => 'corto']))), 'corrección: expediente cerrado antes que motivo corto');
});

prueba('REF-03', 'Caracterización: un error inesperado al verificar el acceso da 500 error_interno en registro y corrección, sin guardar nada', function () use ($firmadaDirecta, $codigo): void {
    $e = new Escenario(acceso: new VerificadorAccesoQueExplota());
    $firmadaDirecta($e, 'nota-carlos', 'exp-carlos', 'epi-100');
    $r1 = $e->kernel->manejar(Escenario::peticion());
    $r2 = $e->kernel->manejar(Escenario::peticionCorreccion('nota-carlos'));
    igual([[500, 'error_interno'], [500, 'error_interno']], [$codigo($r1), $codigo($r2)], 'respuestas');
    afirmar(!str_contains((string) json_encode([$r1->cuerpo, $r2->cuerpo]), 'care_assignments'), 'sin detalles internos');
    igual(['nota-carlos'], array_map(static fn (NotaSoap $n): string => $n->id, $e->notas->todas()), 'nada nuevo guardado');
    igual([], $e->auditoria->eventos(), 'sin eventos');
});

prueba('REF-04', 'Caracterización: si la base falla dentro de la transacción, registro y corrección responden igual y no queda nota, evento ni clave', function () use ($firmadaDirecta, $codigo): void {
    $e = new Escenario(diagnosticos: new RepositorioDiagnosticosQueFalla());
    $firmadaDirecta($e, 'nota-carlos', 'exp-carlos', 'epi-100');
    $r1 = $e->kernel->manejar(Escenario::peticion([], ['idempotency-key' => 'clave-ref-0001']));
    $r2 = $e->kernel->manejar(Escenario::peticionCorreccion('nota-carlos'));
    igual([[500, 'error_persistencia'], [500, 'error_persistencia']], [$codigo($r1), $codigo($r2)], 'respuestas');
    igual(['nota-carlos'], array_map(static fn (NotaSoap $n): string => $n->id, $e->notas->todas()), 'nada nuevo guardado');
    igual([], $e->auditoria->eventos(), 'sin eventos');
    igual(null, $e->almacenes->idempotencia->buscar('hosp-oriente', 'dra-mendez', 'clave-ref-0001'), 'la clave no quedó guardada');
});

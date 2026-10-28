<?php

declare(strict_types=1);

/*
 * Semana 6 — cambio práctico defendido: «Corregir una nota SOAP firmada».
 * Una nota firmada no se modifica (R-07) ni se borra (R-08). La corrección es una VERSIÓN NUEVA
 * que apunta a la anterior (supersedes_id) y explica el motivo (correction_reason).
 * Las pruebas se escribieron antes que el código (rojo → verde) y recorren el sistema completo
 * por el Kernel, igual que una petición real.
 */

use NotasSoap\Dominio\Modelo\NotaSoap;
use NotasSoap\Dominio\Modelo\SeccionesSoap;
use NotasSoap\Pruebas\Dobles\Escenario;
use NotasSoap\Pruebas\Dobles\RepositorioDiagnosticosQueFalla;

/** Nota firmada guardada directo en el almacén (para preparar casos que el flujo normal no permite). */
$firmadaEnAlmacen = static function (Escenario $e, string $id, string $expediente, string $episodio): void {
    $nota = NotaSoap::registrar($id, $expediente, $episodio, 'dra-mendez',
        new SeccionesSoap('Dolor abdominal', 'Abdomen blando', 'Gastritis', 'Omeprazol'), new DateTimeImmutable('2026-10-01T08:00:00-06:00'));
    $e->notas->guardar($nota->firmar(new DateTimeImmutable('2026-10-01T08:10:00-06:00')));
};

prueba('COR-01', 'Corregir una nota firmada crea la versión 2 (supersedes_id + motivo); la original no cambia y queda NOTA_CORREGIDA', function (): void {
    $e = new Escenario();
    $id = Escenario::notaFirmada($e->kernel);

    $r = $e->kernel->manejar(Escenario::peticionCorreccion($id));
    igual(201, $r->estado, 'estado');
    igual([2, 'draft', $id, 'dra-mendez'], [$r->cuerpo['version_no'], $r->cuerpo['status'], $r->cuerpo['supersedes_id'], $r->cuerpo['author_user_ref_id']], 'versión nueva');
    igual('Se anotó la temperatura equivocada: era 37.6 °C, no 38.6 °C', $r->cuerpo['correction_reason'], 'motivo');
    igual(['exp-carlos', 'epi-100'], [$r->cuerpo['medical_record_id'], $r->cuerpo['care_episode_id']], 'hereda expediente y episodio');
    igual('T 37.6 °C, estertores en base derecha', $r->cuerpo['soap']['objetivo'], 'contenido corregido');
    igual('J18.9', $r->cuerpo['diagnoses'][0]['code'] ?? null, 'diagnóstico de la versión nueva');

    $original = $e->kernel->manejar(Escenario::peticion([], [], 'GET', "/api/v1/notas-soap/$id"))->cuerpo;
    igual(['signed', 1, 'T 38.6 °C, estertores en base derecha', null], [$original['status'], $original['version_no'], $original['soap']['objetivo'], $original['supersedes_id']], 'la original sigue igual (R-07)');

    $ultimo = $e->auditoria->eventos()[count($e->auditoria->eventos()) - 1];
    igual(['evento' => 'NOTA_CORREGIDA', 'datos' => ['nota_id' => $r->cuerpo['clinical_note_id'], 'reemplaza_a' => $id, 'autor_id' => 'dra-mendez']], $ultimo, 'auditoría');
});

prueba('COR-02', 'Un borrador no se corrige (se puede firmar o seguir trabajando): 409 nota_no_firmada y no se guarda nada', function (): void {
    $e = new Escenario();
    $id = $e->kernel->manejar(Escenario::peticion())->cuerpo['clinical_note_id'];
    $r = $e->kernel->manejar(Escenario::peticionCorreccion($id));
    igual([409, 'nota_no_firmada'], [$r->estado, $r->cuerpo['error']['code'] ?? null], 'respuesta');
    igual(1, count($e->notas->todas()), 'solo la nota original');
});

prueba('COR-03', 'Cada versión se corrige una sola vez (409 nota_ya_corregida); la corrección firmada sí se puede corregir (versión 3)', function (): void {
    $e = new Escenario();
    $k = $e->kernel;
    $id = Escenario::notaFirmada($k);
    $v2 = $k->manejar(Escenario::peticionCorreccion($id))->cuerpo['clinical_note_id'] ?? null;
    afirmar($v2 !== null, 'primera corrección');

    $r = $k->manejar(Escenario::peticionCorreccion($id, ['correction_reason' => 'Segundo intento de corregir la misma versión']));
    igual([409, 'nota_ya_corregida'], [$r->estado, $r->cuerpo['error']['code'] ?? null], 'segunda corrección de la v1');

    $k->manejar(Escenario::peticion([], [], 'POST', "/api/v1/notas-soap/$v2/firma"));
    $v3 = $k->manejar(Escenario::peticionCorreccion($v2, ['correction_reason' => 'Faltó anotar la alergia a penicilina en el plan']));
    igual([201, 3, $v2], [$v3->estado, $v3->cuerpo['version_no'] ?? null, $v3->cuerpo['supersedes_id'] ?? null], 'cadena v1 → v2 → v3');
});

prueba('COR-04', 'Quién puede corregir: enfermería 403 por la ruta; otro médico 403; token de otro hospital 403', function (): void {
    $e = new Escenario();
    $k = $e->kernel;
    $id = Escenario::notaFirmada($k);
    $ana = $k->manejar(Escenario::peticionCorreccion($id, [], ['authorization' => 'Bearer token-ana']));
    igual([403, 'acceso_denegado'], [$ana->estado, $ana->cuerpo['error']['code'] ?? null], 'enfermería (permiso notas_soap.corregir)');
    igual(403, $k->manejar(Escenario::peticionCorreccion($id, [], ['authorization' => 'Bearer token-lopez']))->estado, 'no es el autor');
    igual(403, $k->manejar(Escenario::peticionCorreccion($id, [], ['x-tenant-id' => 'hosp-central']))->estado, 'otro hospital');
    igual(1, count($e->notas->todas()), 'ninguna corrección guardada');
});

prueba('COR-05', 'Reglas: motivo corto 422 motivo_insuficiente; sección vacía 422; CIE-10 inválido 422; nota inexistente 404; sin efectos', function (): void {
    $e = new Escenario();
    $k = $e->kernel;
    $id = Escenario::notaFirmada($k);
    $codigo = static fn ($r): array => [$r->estado, $r->cuerpo['error']['code'] ?? null];
    igual([422, 'motivo_insuficiente'], $codigo($k->manejar(Escenario::peticionCorreccion($id, ['correction_reason' => 'error']))), 'motivo corto');
    igual([422, 'nota_incompleta'], $codigo($k->manejar(Escenario::peticionCorreccion($id, ['plan' => '  ']))), 'sección vacía');
    igual([422, 'diagnostico_invalido'], $codigo($k->manejar(Escenario::peticionCorreccion($id, ['diagnostico' => ['code' => 'XYZ', 'description' => 'x', 'type' => 'primary']]))), 'CIE-10');
    igual([404, 'nota_no_encontrada'], $codigo($k->manejar(Escenario::peticionCorreccion('no-existe'))), 'inexistente');
    igual([400, 'cuerpo_invalido'], $codigo($k->manejar(Escenario::peticionCorreccion($id, ['correction_reason' => 5]))), 'motivo que no es texto');
    igual(1, count($e->notas->todas()), 'sin correcciones guardadas');
});

prueba('COR-06', 'Si el expediente ya se cerró, la nota no se corrige: 409 expediente_no_disponible (R-03)', function () use ($firmadaEnAlmacen): void {
    $e = new Escenario();
    $firmadaEnAlmacen($e, 'nota-rosa', 'exp-rosa', 'epi-200');
    $r = $e->kernel->manejar(Escenario::peticionCorreccion('nota-rosa'));
    igual([409, 'expediente_no_disponible'], [$r->estado, $r->cuerpo['error']['code'] ?? null], 'respuesta');
});

prueba('COR-07', 'Atomicidad (R-06): si falla el diagnóstico de la corrección, no queda la versión nueva ni NOTA_CORREGIDA', function () use ($firmadaEnAlmacen): void {
    $e = new Escenario(diagnosticos: new RepositorioDiagnosticosQueFalla());
    $firmadaEnAlmacen($e, 'nota-carlos', 'exp-carlos', 'epi-100');
    $r = $e->kernel->manejar(Escenario::peticionCorreccion('nota-carlos'));
    igual([500, 'error_persistencia'], [$r->estado, $r->cuerpo['error']['code'] ?? null], 'respuesta');
    igual(['nota-carlos'], array_map(static fn (NotaSoap $n): string => $n->id, $e->notas->todas()), 'solo la original');
    igual([], $e->auditoria->eventos(), 'sin evento');
});

prueba('COR-08', 'La línea de tiempo muestra las dos versiones y cuál reemplaza a cuál (supersedes_id), sin texto clínico', function (): void {
    $e = new Escenario();
    $k = $e->kernel;
    $id = Escenario::notaFirmada($k);
    $v2 = $k->manejar(Escenario::peticionCorreccion($id))->cuerpo['clinical_note_id'] ?? null;
    $items = $k->manejar(Escenario::peticion([], ['authorization' => 'Bearer token-ana'], 'GET', '/api/v1/expedientes/exp-carlos/notas-soap'))->cuerpo['items'];
    igual([[$id, 1, null], [$v2, 2, $id]], array_map(static fn (array $i): array => [$i['clinical_note_id'], $i['version_no'], array_key_exists('supersedes_id', $i) ? $i['supersedes_id'] : 'falta'], $items), 'versiones');
    afirmar(!str_contains((string) json_encode($items), '37.6'), 'sin texto clínico');
});

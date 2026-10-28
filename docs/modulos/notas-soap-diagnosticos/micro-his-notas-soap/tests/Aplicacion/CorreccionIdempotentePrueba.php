<?php

declare(strict_types=1);

/*
 * Semana 12 — cambio solicitado: «la corrección acepta Idempotency-Key» (brecha B-3).
 * Si se pierde la respuesta de una corrección, el reintento con la misma clave devuelve la MISMA versión
 * nueva (201 + Idempotent-Replayed) en vez de un 409 que el médico no sabe interpretar.
 * Pruebas escritas antes que el código (rojo → verde); recorren el sistema completo por el Kernel.
 */

use NotasSoap\Pruebas\Dobles\Escenario;

$conClave = ['idempotency-key' => 'correccion-movil-0001'];
$versionesDe = static fn (Escenario $e, string $id): int => count(array_filter($e->notas->todas(), static fn ($n): bool => $n->reemplazaA === $id));

prueba('CID-01', 'Reintento con la misma clave: 201 + Idempotent-Replayed y la MISMA versión 2; solo una corrección y un NOTA_CORREGIDA', function () use ($conClave, $versionesDe): void {
    $e = new Escenario();
    $id = Escenario::notaFirmada($e->kernel);
    $r1 = $e->kernel->manejar(Escenario::peticionCorreccion($id, [], $conClave));
    $r2 = $e->kernel->manejar(Escenario::peticionCorreccion($id, [], $conClave));
    igual([201, 201, null, 'true'], [$r1->estado, $r2->estado, $r1->cabecera('Idempotent-Replayed'), $r2->cabecera('Idempotent-Replayed')], 'estados y cabecera');
    igual($r1->cuerpo['clinical_note_id'], $r2->cuerpo['clinical_note_id'] ?? null, 'misma versión nueva');
    igual(1, $versionesDe($e, $id), 'correcciones guardadas');
    igual(1, count(array_filter($e->auditoria->eventos(), static fn (array $ev): bool => $ev['evento'] === 'NOTA_CORREGIDA')), 'eventos NOTA_CORREGIDA');
});

prueba('CID-02', 'La misma clave con otro contenido no es un reintento: 422 idempotency_key_reutilizada y no se guarda nada', function () use ($conClave, $versionesDe): void {
    $e = new Escenario();
    $id = Escenario::notaFirmada($e->kernel);
    $e->kernel->manejar(Escenario::peticionCorreccion($id, [], $conClave));
    $r = $e->kernel->manejar(Escenario::peticionCorreccion($id, ['objetivo' => 'T 36.9 °C, sin estertores'], $conClave));
    igual([422, 'idempotency_key_reutilizada'], [$r->estado, $r->cuerpo['error']['code'] ?? null], 'reutilizada');
    igual(1, $versionesDe($e, $id), 'correcciones guardadas');
});

prueba('CID-03', 'Compatibilidad: sin clave, el segundo envío sigue respondiendo 409 nota_ya_corregida (RF-12 intacto)', function () use ($versionesDe): void {
    $e = new Escenario();
    $id = Escenario::notaFirmada($e->kernel);
    $e->kernel->manejar(Escenario::peticionCorreccion($id));
    $r = $e->kernel->manejar(Escenario::peticionCorreccion($id));
    igual([409, 'nota_ya_corregida', 1], [$r->estado, $r->cuerpo['error']['code'] ?? null, $versionesDe($e, $id)], 'segundo envío sin clave');
});

prueba('CID-04', 'La clave de un registro no sirve para corregir (otra petición): 422 y la nota no se corrige', function () use ($conClave, $versionesDe): void {
    $e = new Escenario();
    $id = Escenario::notaFirmada($e->kernel);
    $e->kernel->manejar(Escenario::peticion(['plan' => 'Control en 48 horas'], $conClave));
    $r = $e->kernel->manejar(Escenario::peticionCorreccion($id, [], $conClave));
    igual([422, 0], [$r->estado, $versionesDe($e, $id)], 'clave de registro');
});

prueba('CID-05', 'Clave con formato inválido → 400; otro médico con la misma clave → 403 (solo el autor) sin ver la corrección ajena', function () use ($conClave): void {
    $e = new Escenario();
    $id = Escenario::notaFirmada($e->kernel);
    igual(400, $e->kernel->manejar(Escenario::peticionCorreccion($id, [], ['idempotency-key' => 'corta']))->estado, 'formato');
    $e->kernel->manejar(Escenario::peticionCorreccion($id, [], $conClave));
    $r = $e->kernel->manejar(Escenario::peticionCorreccion($id, [], $conClave + ['authorization' => 'Bearer token-lopez']));
    igual([403, false], [$r->estado, isset($r->cuerpo['clinical_note_id'])], 'otro médico');
});

<?php

declare(strict_types=1);

use NotasSoap\Pruebas\Dobles\Escenario;
use NotasSoap\Pruebas\Dobles\VerificadorAccesoQueExplota;

prueba('HTTP-01', 'POST /api/v1/notas-soap válido responde 201 con la nota en borrador', function (): void {
    $r = (new Escenario())->kernel->manejar(Escenario::peticion());
    igual(201, $r->estado, 'estado');
    igual('draft', $r->cuerpo['status'], 'status');
    igual('J18.9', $r->cuerpo['diagnoses'][0]['code'], 'diagnóstico');
});

prueba('HTTP-02', 'Sin X-Tenant-ID responde 400; sin token o con token desconocido responde 401 (CA-02)', function (): void {
    $k = (new Escenario())->kernel;
    igual(400, $k->manejar(Escenario::peticion([], ['x-tenant-id' => '']))->estado, 'sin tenant');
    igual(401, $k->manejar(Escenario::peticion([], ['authorization' => '']))->estado, 'sin token');
    igual(401, $k->manejar(Escenario::peticion([], ['authorization' => 'Bearer inventado']))->estado, 'token desconocido');
});

prueba('HTTP-03', 'Cada error de dominio se traduce a su código HTTP (403, 409, 422)', function (): void {
    $k = (new Escenario())->kernel;
    igual(403, $k->manejar(Escenario::peticion([], ['authorization' => 'Bearer token-lopez']))->estado, 'no asignado');
    igual(409, $k->manejar(Escenario::peticion(['medical_record_id' => 'exp-rosa']))->estado, 'expediente cerrado');
    igual(422, $k->manejar(Escenario::peticion(['plan' => '']))->estado, 'sección vacía');
    $r = $k->manejar(Escenario::peticion(['diagnostico' => ['code' => 'XYZ', 'description' => 'x', 'type' => 'primary']]));
    igual([422, 'diagnostico_invalido'], [$r->estado, $r->cuerpo['error']['code']], 'CIE-10 inválido');
});

prueba('HTTP-04', 'Cuerpo sin objeto diagnostico responde 400 cuerpo_invalido', function (): void {
    $r = (new Escenario())->kernel->manejar(Escenario::peticion(['diagnostico' => 'J18.9']));
    igual([400, 'cuerpo_invalido'], [$r->estado, $r->cuerpo['error']['code']], 'cuerpo');
});

prueba('HTTP-05', 'Firmar y consultar por HTTP; firmar dos veces responde 409', function (): void {
    $k = (new Escenario())->kernel;
    $id = $k->manejar(Escenario::peticion())->cuerpo['clinical_note_id'];
    igual(200, $k->manejar(Escenario::peticion([], [], 'POST', "/api/v1/notas-soap/$id/firma"))->estado, 'firma');
    igual(409, $k->manejar(Escenario::peticion([], [], 'POST', "/api/v1/notas-soap/$id/firma"))->estado, 'segunda firma');
    $c = $k->manejar(Escenario::peticion([], [], 'GET', "/api/v1/notas-soap/$id"));
    igual([200, 'signed'], [$c->estado, $c->cuerpo['status']], 'consulta');
    igual(404, $k->manejar(Escenario::peticion([], [], 'GET', '/api/v1/notas-soap/no-existe'))->estado, 'nota inexistente');
});

prueba('HTTP-06', 'Ruta inexistente 404, método equivocado 405 y error inesperado 500 sin detalles', function (): void {
    $e = new Escenario();
    igual(404, $e->kernel->manejar(Escenario::peticion([], [], 'GET', '/api/v1/otra'))->estado, 'ruta');
    igual(405, $e->kernel->manejar(Escenario::peticion([], [], 'DELETE'))->estado, 'método');
    $r = (new Escenario(acceso: new VerificadorAccesoQueExplota()))->kernel->manejar(Escenario::peticion());
    igual([500, 'error_interno'], [$r->estado, $r->cuerpo['error']['code']], 'inesperado');
    afirmar(!str_contains((string) json_encode($r->cuerpo), 'care_assignments'), 'no expone el mensaje interno');
});

prueba('HTTP-07', 'El autor sale del token, no del cuerpo: enviar author_user_ref_id no cambia la autoría (RF-06, CA-12)', function (): void {
    $e = new Escenario();
    $r = $e->kernel->manejar(Escenario::peticion(['author_user_ref_id' => 'dr-lopez']));
    igual([201, 'dra-mendez'], [$r->estado, $r->cuerpo['author_user_ref_id']], 'autor en la respuesta');
    igual('dra-mendez', $e->notas->buscar($r->cuerpo['clinical_note_id'])?->autorId, 'autor guardado');
});

prueba('HTTP-08', 'MVC: la misma acción con Accept: text/html usa la vista HTML (mismo controlador, mismo modelo)', function (): void {
    $k = (new Escenario())->kernel;
    $id = $k->manejar(Escenario::peticion())->cuerpo['clinical_note_id'];
    $r = $k->manejar(Escenario::peticion([], ['accept' => 'text/html'], 'GET', "/api/v1/notas-soap/$id"));
    afirmar($r->esHtml(), 'debe ser HTML');
    igual(200, $r->estado, 'estado');
    foreach (['<h1>Nota SOAP</h1>', 'Borrador', 'J18.9', 'Probable neumonía adquirida en la comunidad'] as $texto) {
        afirmar(str_contains((string) $r->html, $texto), "el HTML debe contener: $texto");
    }
    $json = $k->manejar(Escenario::peticion([], [], 'GET', "/api/v1/notas-soap/$id"));
    afirmar(!$json->esHtml() && $json->cuerpo['soap']['plan'] === 'Radiografía de tórax y antibiótico', 'sin Accept sigue siendo JSON');
});

prueba('HTTP-09', 'La vista HTML escapa lo que escribió el usuario: un <script> en una sección se muestra como texto', function (): void {
    $k = (new Escenario())->kernel;
    $id = $k->manejar(Escenario::peticion(['subjetivo' => '<script>alert(1)</script> dolor']))->cuerpo['clinical_note_id'];
    $html = (string) $k->manejar(Escenario::peticion([], ['accept' => 'text/html'], 'GET', "/api/v1/notas-soap/$id"))->html;
    afirmar(!str_contains($html, '<script>'), 'no debe haber etiqueta script sin escapar');
    afirmar(str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt; dolor'), 'debe aparecer escapado');
});

prueba('HTTP-10', 'Entrada: un campo que no es texto es error de formato (400); los errores también salen en HTML si se pide', function (): void {
    $k = (new Escenario())->kernel;
    $r = $k->manejar(Escenario::peticion(['plan' => ['no', 'es', 'texto']]));
    igual([400, 'cuerpo_invalido'], [$r->estado, $r->cuerpo['error']['code']], 'formato');
    afirmar(str_contains($r->cuerpo['error']['message'], '"plan"'), 'el mensaje nombra el campo');
    $h = $k->manejar(Escenario::peticion([], ['accept' => 'text/html', 'authorization' => 'Bearer token-lopez']));
    afirmar($h->esHtml() && $h->estado === 403 && str_contains((string) $h->html, 'acceso_denegado'), 'error 403 en HTML');
});

/* ---------- Semana 5: cliente-servidor ---------- */

prueba('HTTP-11', 'Idempotency-Key repetida: 201 con Idempotent-Replayed: true y la misma nota', function (): void {
    $k = (new Escenario())->kernel;
    $r1 = $k->manejar(Escenario::peticion([], ['idempotency-key' => 'clave-http-0001']));
    $r2 = $k->manejar(Escenario::peticion([], ['idempotency-key' => 'clave-http-0001']));
    igual([201, 201, null, 'true'], [$r1->estado, $r2->estado, $r1->cabecera('Idempotent-Replayed'), $r2->cabecera('Idempotent-Replayed')], 'estados y cabecera');
    igual($r1->cuerpo['clinical_note_id'], $r2->cuerpo['clinical_note_id'], 'misma nota');
});

prueba('HTTP-12', 'Idempotency-Key con formato inválido → 400; reutilizada con otro cuerpo → 422 idempotency_key_reutilizada', function (): void {
    $k = (new Escenario())->kernel;
    igual(400, $k->manejar(Escenario::peticion([], ['idempotency-key' => 'corta']))->estado, 'formato');
    $k->manejar(Escenario::peticion([], ['idempotency-key' => 'clave-http-0002']));
    $r = $k->manejar(Escenario::peticion(['plan' => 'Otro plan'], ['idempotency-key' => 'clave-http-0002']));
    igual([422, 'idempotency_key_reutilizada'], [$r->estado, $r->cuerpo['error']['code']], 'reutilizada');
});

prueba('HTTP-13', 'Permiso por ruta: enfermería consulta (200) pero no registra ni firma (403 antes de llegar al caso de uso)', function (): void {
    $e = new Escenario();
    $id = $e->kernel->manejar(Escenario::peticion())->cuerpo['clinical_note_id'];
    $ana = ['authorization' => 'Bearer token-ana'];
    igual(200, $e->kernel->manejar(Escenario::peticion([], $ana, 'GET', "/api/v1/notas-soap/$id"))->estado, 'consulta');
    igual(403, $e->kernel->manejar(Escenario::peticion([], $ana))->estado, 'registro');
    igual(403, $e->kernel->manejar(Escenario::peticion([], $ana, 'POST', "/api/v1/notas-soap/$id/firma"))->estado, 'firma');
    igual(1, count($e->notas->todas()), 'no se creó nada más');
});

prueba('HTTP-14', 'GET /api/v1/expedientes/{id}/notas-soap: resúmenes sin texto clínico, filtro inválido → 400, 405 con cabecera Allow', function (): void {
    $k = (new Escenario())->kernel;
    $k->manejar(Escenario::peticion());
    $r = $k->manejar(Escenario::peticion([], [], 'GET', '/api/v1/expedientes/exp-carlos/notas-soap'));
    igual([200, 'exp-carlos', 1, ['J18.9']], [$r->estado, $r->cuerpo['medical_record_id'], count($r->cuerpo['items']), $r->cuerpo['items'][0]['diagnosis_codes']], 'listado');
    afirmar(!array_key_exists('soap', $r->cuerpo['items'][0]), 'sin secciones SOAP');
    igual(400, $k->manejar(Escenario::peticion([], [], 'GET', '/api/v1/expedientes/exp-carlos/notas-soap', ['estado' => 'x']))->estado, 'filtro');
    $m = $k->manejar(Escenario::peticion([], [], 'DELETE', '/api/v1/expedientes/exp-carlos/notas-soap'));
    igual([405, 'GET'], [$m->estado, $m->cabecera('Allow')], '405');
});

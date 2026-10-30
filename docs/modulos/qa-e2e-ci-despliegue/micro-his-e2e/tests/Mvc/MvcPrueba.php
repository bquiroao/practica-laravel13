<?php

declare(strict_types=1);

use E2e\Aplicacion\Resultados\Salida;
use E2e\Presentacion\Http\Formularios\EntradaInvalida;
use E2e\Presentacion\Http\Formularios\FormularioResultado;
use E2e\Presentacion\Http\Formularios\FormularioVersion;
use E2e\Presentacion\Http\Solicitud;
use E2e\Presentacion\Http\Vistas\SalidaVista;

$solicitud = static fn (string $cuerpo, array $cabeceras = []): Solicitud => new Solicitud('POST', '/x', $cabeceras, $cuerpo);

prueba('MVC-01', 'Formulario de versión: solo FORMA. Tipos equivocados y obligatorios ausentes → 400 con los campos; un digest con mal formato PASA (lo rechaza el dominio con 422)', function () use ($solicitud): void {
    $e = lanza(EntradaInvalida::class, static fn () => FormularioVersion::registrar($solicitud('{"numero": 2, "modulos": "admision"}')), 'tipos');
    igual(['CUERPO_INVALIDO', ['numero', 'commit', 'digest', 'modulos']], [$e->codigo, $e->campos], 'campos');
    igual('no-es-un-digest', FormularioVersion::registrar($solicitud('{"numero":"2.4.0","commit":"c56f169","digest":"no-es-un-digest","modulos":["x"]}'))['digest'], 'la regla no es del formulario');
});

prueba('MVC-02', 'Formulario de resultado: Idempotency-Key obligatoria, intentos entero, base64 estricto con el índice del artefacto', function () use ($solicitud): void {
    $ok = '{"escenario":"E2E-101","resultado":"PASA","intentos":1,"evidencias":[{"tipo":"log","contenido_base64":"' . base64_encode('ok') . '"},{"tipo":"log","contenido_base64":"@@"}]}';
    igual('IDEMPOTENCY_KEY_REQUERIDA', lanza(EntradaInvalida::class, static fn () => FormularioResultado::desde($solicitud($ok)), 'clave')->codigo, 'sin clave');
    igual(['BASE64_INVALIDO', ['evidencias[1]']], [($e = lanza(EntradaInvalida::class, static fn () => FormularioResultado::desde($solicitud($ok, ['idempotency-key' => 'k-00000001'])), 'b64'))->codigo,
        $e->campos], 'base64');
    igual(['intentos'], lanza(EntradaInvalida::class, static fn () => FormularioResultado::desde($solicitud('{"escenario":"E2E-101","resultado":"PASA","intentos":"1"}',
        ['idempotency-key' => 'k-00000001'])), 'intentos')->campos, '«1» en texto no es un entero');
});

prueba('MVC-03', 'Vista: cada tipo de Salida tiene su código HTTP; Location solo en 201; los errores usan «error» y los éxitos «codigo»', function (): void {
    $v = new SalidaVista();
    $esperados = [Salida::CREADO => 201, Salida::OK => 200, Salida::INVALIDO => 422, Salida::CONFLICTO => 409, Salida::NO_ENCONTRADO => 404, Salida::DENEGADO => 403,
        Salida::DEMASIADO_GRANDE => 413, Salida::NO_DISPONIBLE => 503];
    foreach ($esperados as $tipo => $estado) {
        $r = $v->mostrar(new Salida($tipo, 'X'), '/loc');
        igual([$estado, $estado >= 400 ? 'error' : 'codigo', $estado === 201], [$r->estado, array_key_first($r->cuerpo), isset($r->cabeceras['Location'])], $tipo);
    }
});

prueba('MVC-04', 'Controlador: una entrada mal formada se responde 400 SIN llamar al caso de uso (nada escrito, nada trazado)', function (): void {
    $e = new Escenario();
    $antes = $e->auditoriaReal()->acciones();
    $r = $e->kernel()->manejar(new Solicitud('POST', '/api/v1/e2e/versiones', ['authorization' => 'Bearer token-pipeline', 'x-tenant-id' => 'hosp-norte'],
        '{"numero":"2.4.0","commit":"c56f169","digest":"sha256:x","modulos":"admision"}'));
    igual([400, ['modulos'], $antes], [$r->estado, $r->cuerpo['campos'], $e->auditoriaReal()->acciones()], 'sin efectos');
});

prueba('MVC-05', 'Kernel: 405 si la ruta existe con otro método; Location en los 201 de versión y de ejecución', function (): void {
    $e = new Escenario();
    $k = $e->kernel();
    $c = ['authorization' => 'Bearer token-pipeline', 'x-tenant-id' => 'hosp-norte'];
    igual(405, $k->manejar(new Solicitud('GET', '/api/v1/e2e/versiones/2.4.0/gate', $c))->estado, 'método');
    $v = $k->manejar(new Solicitud('POST', '/api/v1/e2e/versiones', $c, json_encode(['numero' => '2.4.0', 'commit' => 'c56f169', 'digest' => Escenario::DIGEST, 'modulos' => ['admision']])));
    igual('/api/v1/e2e/versiones/2.4.0/trazabilidad', $v->cabeceras['Location'], 'Location de la versión');
});

<?php

declare(strict_types=1);

use Qa\Aplicacion\Resultados\Salida;
use Qa\Presentacion\Http\Formularios\EntradaInvalida;
use Qa\Presentacion\Http\Formularios\FormularioCaso;
use Qa\Presentacion\Http\Formularios\FormularioResultado;
use Qa\Presentacion\Http\Solicitud;
use Qa\Presentacion\Http\Vistas\SalidaVista;

$solicitud = static fn (array|string $cuerpo, array $cabeceras = []): Solicitud => new Solicitud('POST', '/x', $cabeceras,
    is_string($cuerpo) ? $cuerpo : json_encode($cuerpo));

prueba('MVC-01', 'Formulario de caso: solo FORMA; tipos equivocados y obligatorios ausentes → 400 con los campos; un título vacío pasa (lo rechaza el dominio)', function () use ($solicitud): void {
    $e = lanza(EntradaInvalida::class, static fn () => FormularioCaso::desde($solicitud(['id' => 7, 'titulo' => 'x', 'pasos' => 'uno', 'prioridad' => 'ALTA'])), 'tipos');
    igual(['CUERPO_INVALIDO', ['id', 'pasos', 'requisitos']], [$e->codigo, $e->campos], 'campos');
    $ok = FormularioCaso::desde($solicitud(['id' => 'TC-1', 'titulo' => '', 'pasos' => [], 'prioridad' => 'X', 'requisitos' => []]));
    igual('', $ok['titulo'], 'la regla no es del formulario');
});

prueba('MVC-02', 'Formulario de resultado: Idempotency-Key obligatoria, base64 estricto, verifica_defecto entero', function () use ($solicitud): void {
    $sinClave = lanza(EntradaInvalida::class, static fn () => FormularioResultado::desde($solicitud(['caso' => 'TC-107', 'resultado' => 'PASA'])), 'clave');
    $b64 = lanza(EntradaInvalida::class, static fn () => FormularioResultado::desde($solicitud(['caso' => 'TC-107', 'resultado' => 'FALLA',
        'evidencias' => [['tipo' => 'captura', 'contenido_base64' => 'no*es*base64']]], ['idempotency-key' => 'mvc-000001'])), 'base64');
    $tipo = lanza(EntradaInvalida::class, static fn () => FormularioResultado::desde($solicitud(['caso' => 'TC-107', 'resultado' => 'PASA',
        'verifica_defecto' => '1'], ['idempotency-key' => 'mvc-000001'])), 'tipo');
    igual(['IDEMPOTENCY_KEY_REQUERIDA', ['evidencias[0]'], ['verifica_defecto']], [$sinClave->codigo, $b64->campos, $tipo->campos], 'errores');
    $ok = FormularioResultado::desde($solicitud(['caso' => 'TC-107', 'resultado' => 'FALLA', 'evidencias' => [['tipo' => 'captura',
        'contenido_base64' => base64_encode(Escenario::PNG)]]], ['idempotency-key' => 'mvc-000001']));
    igual(['mvc-000001', Escenario::PNG, 'GENERAL'], [$ok['clave'], $ok['datos']['evidencias'][0]['bytes'], $ok['datos']['evidencias'][0]['clasificacion']], 'decodificado');
});

prueba('MVC-03', 'Vista: cada tipo de salida tiene su código HTTP; Location solo en 201; los errores usan la clave «error»', function (): void {
    $v = new SalidaVista();
    $estados = [];
    foreach ([Salida::CREADO, Salida::OK, Salida::INVALIDO, Salida::CONFLICTO, Salida::NO_ENCONTRADO, Salida::DENEGADO, Salida::DEMASIADO_GRANDE, Salida::NO_DISPONIBLE] as $t) {
        $estados[] = $v->mostrar(new Salida($t, 'X'), '/loc')->estado;
    }
    igual([201, 200, 422, 409, 404, 403, 413, 503], $estados, 'estados');
    igual([['Location' => '/loc'], [], ['error' => 'X']], [$v->mostrar(new Salida(Salida::CREADO, 'X'), '/loc')->cabeceras,
        $v->mostrar(new Salida(Salida::OK, 'X'), '/loc')->cabeceras, $v->mostrar(new Salida(Salida::CONFLICTO, 'X'))->cuerpo], 'cabeceras y cuerpo');
});

prueba('MVC-04', 'Controlador: una entrada mal formada se responde 400 SIN llamar al caso de uso (nada escrito, nada trazado)', function (): void {
    $e = new Escenario();
    $id = $e->campanaEnCurso();
    $antes = count($e->memAuditoria()->acciones());
    $r = $e->kernel()->manejar(new Solicitud('POST', "/api/v1/qa/campanas/$id/resultados", ['authorization' => 'Bearer token-tito-tester',
        'x-tenant-id' => 'hosp-norte', 'idempotency-key' => 'mvc-000002'], json_encode(['caso' => 'TC-107', 'resultado' => ['PASA']])));
    igual([400, 'CUERPO_INVALIDO', ['resultado']], [$r->estado, $r->cuerpo['error'], $r->cuerpo['detalle']], 'respuesta');
    igual([$antes, 0], [count($e->memAuditoria()->acciones()), count($e->intentos())], 'sin efectos');
});

prueba('MVC-05', 'Modo «memoria» de producción: el mismo Kernel funciona sin base (QA_PERSISTENCIA=memoria, datos-demo.php)', function (): void {
    $config = ['persistencia' => 'memoria'] + (require dirname(__DIR__, 2) . '/config/config.php');
    $k = crearKernel($config);
    $h = ['authorization' => 'Bearer token-lider-qa', 'x-tenant-id' => 'hosp-norte'];
    $p = $k->manejar(new Solicitud('POST', '/api/v1/qa/campanas', $h, json_encode(['nombre' => 'Demo', 'funcionalidades' => ['expediente'], 'casos' => ['TC-109']])));
    $i = $k->manejar(new Solicitud('POST', '/api/v1/qa/campanas/1/inicio', $h, json_encode(['build' => '1.4.0'])));
    igual([201, 422, ['TC-107', 'TC-108']], [$p->estado, $i->estado, $i->cuerpo['detalle']], 'la matriz de datos-demo.php se aplica');
});

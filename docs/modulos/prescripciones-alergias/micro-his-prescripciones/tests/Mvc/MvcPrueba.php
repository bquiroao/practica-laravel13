<?php

declare(strict_types=1);

use Prescripciones\Aplicacion\Resultados\Resultado;
use Prescripciones\Presentacion\Http\Controladores\PrescripcionesControlador;
use Prescripciones\Presentacion\Http\Formularios\EntradaInvalida;
use Prescripciones\Presentacion\Http\Formularios\FormularioPrescripcion;
use Prescripciones\Presentacion\Http\Identidad;
use Prescripciones\Presentacion\Http\Solicitud;
use Prescripciones\Presentacion\Http\Vistas\PrescripcionVista;

prueba('MVC-01', 'Formulario: usuario y hospital salen de la Identidad; lo que diga el cuerpo se ignora', function (): void {
    $solicitud = new Solicitud('POST', '/api/v1/prescripciones', ['idempotency-key' => 'k-1'], (string) json_encode([
        'paciente' => 'P-005', 'items' => [Escenario::item('PARA500')], 'hospital' => 'hosp-sur', 'usuario' => 'dr-sur',
    ]));
    $comando = FormularioPrescripcion::emision($solicitud, new Identidad('dr-lopez', 'hosp-norte'));
    igual(['dr-lopez', 'hosp-norte', 'k-1', 'P-005'], [$comando->usuario, $comando->hospital, $comando->clave, $comando->paciente], 'comando');
    $e = lanza(EntradaInvalida::class, static fn () => FormularioPrescripcion::emision(new Solicitud('POST', '/', ['idempotency-key' => 'clave con espacios'], '{}'),
        new Identidad('u', 'h')), 'clave inválida');
    igual('IDEMPOTENCY_KEY_REQUERIDA', $e->codigo, 'código');
});

prueba('MVC-02', 'Vista: Location solo en 201; «codigo» en éxito y «error» en fallo; cada tipo con su estado', function (): void {
    $creada = PrescripcionVista::emision(new Resultado(Resultado::CREADO, 'EMITIDA', ['id' => 7]));
    $reintento = PrescripcionVista::emision(new Resultado(Resultado::OK, 'YA_EMITIDA', ['id' => 7]));
    $bloqueo = PrescripcionVista::emision(new Resultado(Resultado::RECHAZADO, 'BLOQUEO_ALERGIA'));
    igual([201, ['Location' => '/api/v1/prescripciones/7'], 'EMITIDA'], [$creada->estado, $creada->cabeceras, $creada->cuerpo['codigo']], 'creada');
    igual([200, []], [$reintento->estado, $reintento->cabeceras], 'reintento');
    igual([422, 'BLOQUEO_ALERGIA'], [$bloqueo->estado, $bloqueo->cuerpo['error']], 'bloqueo');
    $estados = array_map(static fn (string $t): int => PrescripcionVista::detalle(new Resultado($t, 'X'))->estado,
        [Resultado::OK, Resultado::RECHAZADO, Resultado::CONFLICTO, Resultado::NO_ENCONTRADO, Resultado::DENEGADO, Resultado::NO_DISPONIBLE]);
    igual([200, 422, 409, 404, 403, 503], $estados, 'estados');
});

prueba('MVC-03', 'Controlador: recibe solo casos de uso (Model), nada de persistencia ni de otras capas', function (): void {
    foreach ((new \ReflectionClass(PrescripcionesControlador::class))->getConstructor()->getParameters() as $p) {
        afirmar(str_starts_with((string) $p->getType(), 'Prescripciones\\Aplicacion\\CasosDeUso\\'), 'recibe ' . $p->getType());
    }
});

prueba('MVC-04', 'Formato frente a regla: JSON roto → 400 del formulario; dosis 0 → 422 del Dominio, en el mismo endpoint', function (): void {
    $k = (new Escenario())->kernel();
    $formato = $k->manejar(new Solicitud('POST', '/api/v1/prescripciones', ['authorization' => 'Bearer token-dr-lopez', 'x-tenant-id' => 'hosp-norte',
        'idempotency-key' => 'k'], '{"paciente": "P-005", "items": [}'));
    $regla = $k->manejar(Escenario::http('POST', '/api/v1/prescripciones', ['paciente' => 'P-005', 'items' => [Escenario::item('PARA500', 0)]],
        cabeceras: ['idempotency-key' => 'k']));
    igual([[400, 'JSON_INVALIDO'], [422, 'DATOS_INVALIDOS']], [[$formato->estado, $formato->cuerpo['error']], [$regla->estado, $regla->cuerpo['error']]], 'respuestas');
});

prueba('MVC-05', 'Modo «memoria» de producción: crearKernel funciona de punta a punta sin base de datos', function (): void {
    $config = Escenario::config();
    $config['persistencia'] = 'memoria';
    $config['db']['dsn'] = 'pgsql:host=127.0.0.1;port=1;dbname=no-se-usa';
    $k = crearKernel($config);
    $emitida = $k->manejar(Escenario::http('POST', '/api/v1/prescripciones', ['paciente' => 'P-005', 'items' => [Escenario::item('PARA500')]],
        cabeceras: ['idempotency-key' => 'mem-1']));
    $consulta = $k->manejar(Escenario::http('GET', '/api/v1/prescripciones/1', token: 'token-farmacia-norte'));
    igual([201, 200, 500.0], [$emitida->estado, $consulta->estado, $consulta->cuerpo['items'][0]['dosis']], 'respuestas');
    lanza(\InvalidArgumentException::class, static fn () => crearKernel(['persistencia' => 'otra'] + $config), 'modo desconocido');
});

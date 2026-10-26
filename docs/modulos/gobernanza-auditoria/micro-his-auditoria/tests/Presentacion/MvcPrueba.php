<?php

declare(strict_types=1);

use Auditoria\Aplicacion\Resultados\Resultado;
use Auditoria\Presentacion\Http\Controladores\AuditoriaControlador;
use Auditoria\Presentacion\Http\Formularios\EntradaInvalida;
use Auditoria\Presentacion\Http\Formularios\FormularioMovimientos;
use Auditoria\Presentacion\Http\Identidad;
use Auditoria\Presentacion\Http\Solicitud;
use Auditoria\Presentacion\Http\Vistas\ErrorVista;
use Auditoria\Presentacion\Http\Vistas\MovimientosVista;
use Auditoria\Pruebas\Dobles\Escenario;

$mvcEmisor = new Identidad('svc-pacientes', Escenario::H_ORIENTE);

prueba('MVC-01', 'Formulario: solo FORMATO (cuerpo ausente o lista → 400); el emisor y el hospital salen del token y la cabecera, nunca del cuerpo', function () use ($mvcEmisor): void {
    foreach ([null, ['a', 'b']] as $cuerpo) {
        $e = lanza(EntradaInvalida::class, fn () => FormularioMovimientos::registro(new Solicitud('POST', '/', ['X-Tenant-ID' => Escenario::H_ORIENTE], [], $cuerpo),
            $mvcEmisor), json_encode($cuerpo));
        igual(EntradaInvalida::CUERPO, $e->tipo, 'tipo');
    }
    $comando = FormularioMovimientos::registro(new Solicitud('POST', '/', ['X-Tenant-ID' => Escenario::H_ORIENTE], [],
        Escenario::mov(['emisor' => 'svc-rbac'])), $mvcEmisor);
    igual(['svc-pacientes', Escenario::H_ORIENTE], [$comando->emisor, $comando->hospitalToken], 'el «emisor» del cuerpo se ignora');
    igual(EntradaInvalida::FORMATO, lanza(EntradaInvalida::class, fn () => FormularioMovimientos::verificacion(
        new Solicitud('POST', '/', [], [], ['hospital' => 7]), $mvcEmisor), 'hospital numérico')->tipo, 'verificación');
});

prueba('MVC-02', 'Vistas: único lugar con códigos HTTP; Location solo en el 201; cada categoría de error a su código', function (): void {
    $creado = MovimientosVista::registro(Resultado::creado(['id' => 5, 'secuencia' => 1, 'sello' => 'x']));
    $repetido = MovimientosVista::registro(Resultado::ok(['id' => 5], 'YA_REGISTRADO'));
    igual([201, '/api/v1/auditoria/movimientos/5', 200, false], [$creado->estado, $creado->cabeceras['Location'], $repetido->estado,
        isset($repetido->cabeceras['Location'])], 'registro');
    $codigos = [];
    foreach ([Resultado::INVALIDO, Resultado::PROHIBIDO, Resultado::CONFLICTO, Resultado::NO_ENCONTRADO, Resultado::NO_DISPONIBLE] as $categoria) {
        $codigos[$categoria] = ErrorVista::deResultado(Resultado::error($categoria, 'X'))->estado;
    }
    igual(['invalido' => 422, 'prohibido' => 403, 'conflicto' => 409, 'no_encontrado' => 404, 'no_disponible' => 503], $codigos, 'categorías');
});

prueba('MVC-03', 'El controlador funciona sin Kernel ni rutas: se prueba solo, con el Modelo real (casos de uso en memoria)', function () use ($mvcEmisor): void {
    $e = Escenario::crear();
    $c = $e->casos;
    $controlador = new AuditoriaControlador($c['registrar'], $c['consultar'], $c['detalle'], $c['verificar']);
    $r = $controlador->registrar(new Solicitud('POST', '/', ['X-Tenant-ID' => Escenario::H_ORIENTE], [], Escenario::mov()), $mvcEmisor);
    igual([201, 1], [$r->estado, $r->cuerpo['secuencia']], 'registro');
    $d = $controlador->verDetalle(new Solicitud('GET', '/', ['X-Tenant-ID' => Escenario::H_ORIENTE]), new Identidad('auditor-oriente', Escenario::H_ORIENTE), ['id' => '1']);
    igual([200, 'u-ana'], [$d->estado, $d->cuerpo['movimiento']['usuario']], 'detalle');
});

prueba('MVC-04', 'Formato válido pero regla rota llega al Dominio: 422 DATOS_INVALIDOS (no 400 del formulario)', function (): void {
    $r = Escenario::crear()->kernel->manejar(Escenario::http('POST', '/api/v1/auditoria/movimientos', 'token-svc-pacientes-oriente', Escenario::H_ORIENTE,
        Escenario::mov(['resultado' => 'quizas'])));
    igual([422, 'DATOS_INVALIDOS', ['resultado']], [$r->estado, $r->cuerpo['error'], array_keys($r->cuerpo['campos'])], 'regla de dominio');
});

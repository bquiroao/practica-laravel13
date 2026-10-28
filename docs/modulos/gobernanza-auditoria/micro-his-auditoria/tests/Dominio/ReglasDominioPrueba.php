<?php

declare(strict_types=1);

use Auditoria\Dominio\Filtros\FiltroModulo;
use Auditoria\Dominio\Filtros\FiltroRangoFechas;
use Auditoria\Dominio\Filtros\FiltroResultado;
use Auditoria\Dominio\Filtros\FiltroUsuario;
use Auditoria\Dominio\Modelo\CatalogoAcciones;
use Auditoria\Dominio\Modelo\ConsultaMovimientos;
use Auditoria\Dominio\Modelo\Movimiento;
use Auditoria\Dominio\Modelo\Sello;
use Auditoria\Dominio\Modelo\SolicitudRegistro;
use Auditoria\Dominio\Reglas\ReglaAccionEnCatalogo;
use Auditoria\Dominio\Reglas\ReglaCamposObligatorios;
use Auditoria\Dominio\Reglas\ReglaFechaNoFutura;
use Auditoria\Dominio\Reglas\ReglaFormatoCampos;
use Auditoria\Dominio\Reglas\ReglaModuloDelEmisor;
use Auditoria\Dominio\Reglas\ReglaResultadoValido;
use Auditoria\Dominio\Reglas\ReglaSinDatosProhibidos;
use Auditoria\Dominio\Reglas\ReglaTenantDelEmisor;
use Auditoria\Pruebas\Dobles\Escenario;
use Auditoria\Pruebas\Dobles\RelojFijo;

$domSolicitud = static fn (array $cambios = [], string $emisor = 'svc-pacientes'): SolicitudRegistro =>
    new SolicitudRegistro($emisor, Escenario::H_ORIENTE, Escenario::mov($cambios));
$domMov = static fn (array $cambios = []): Movimiento => Movimiento::desdeSolicitud($domSolicitud($cambios));

prueba('DOM-01', 'Movimiento: ocurrido_en pasa a UTC; el detalle se canoniza (mismas claves en otro orden = mismo contenido)', function () use ($domMov): void {
    $m = $domMov(['ocurrido_en' => '2026-10-21T09:15:00-06:00']);
    igual('2026-10-21T15:15:00Z', $m->contenido()['ocurrido_en'], 'UTC');
    $a = $domMov(['detalle' => ['b' => 2, 'a' => ['y' => 1, 'x' => 0]]]);
    $b = $domMov(['detalle' => ['a' => ['x' => 0, 'y' => 1], 'b' => 2]]);
    afirmar($a->mismoContenidoQue($b), 'el orden de las claves del detalle cambia el contenido');
    igual(['a', 'b'], array_keys($a->contenido()['detalle']), 'claves ordenadas');
    afirmar(!$a->mismoContenidoQue($domMov(['detalle' => ['a' => 1]])), 'contenidos distintos se ven iguales');
});

prueba('DOM-02', 'Reglas de registro, cada una aislada: tenant, campos, módulo del emisor, formato, catálogo, resultado, fecha y datos prohibidos', function () use ($domSolicitud): void {
    $codigo = static fn ($regla, SolicitudRegistro $s): ?string => $regla->evaluar($s)?->codigo;
    igual([null, 'TENANT_NO_COINCIDE'], [$codigo(new ReglaTenantDelEmisor(), $domSolicitud()),
        $codigo(new ReglaTenantDelEmisor(), $domSolicitud(['hospital' => Escenario::H_NORTE]))], 'tenant');
    igual(['event_id', 'usuario'], array_keys((new ReglaCamposObligatorios())->evaluar($domSolicitud(['event_id' => ' ', 'usuario' => null]))->campos), 'obligatorios');
    $modulo = new ReglaModuloDelEmisor(['svc-pacientes' => 'pacientes']);
    igual([null, 'MODULO_NO_PERMITIDO', 'MODULO_NO_PERMITIDO'], [$codigo($modulo, $domSolicitud()), $codigo($modulo, $domSolicitud(['modulo' => 'rbac'])),
        $codigo($modulo, $domSolicitud([], 'svc-desconocido'))], 'módulo del emisor (cuenta desconocida = fail-closed)');
    $formato = new ReglaFormatoCampos();
    igual([null, 'DATOS_INVALIDOS'], [$codigo($formato, $domSolicitud(['usuario' => str_repeat('ñ', 64)])),
        $codigo($formato, $domSolicitud(['usuario' => str_repeat('ñ', 65)]))], 'usuario de 64 / 65 caracteres');
    igual(['detalle'], array_keys($formato->evaluar($domSolicitud(['detalle' => ['lista', 'no objeto']]))->campos), 'detalle que es lista');
    igual(['detalle'], array_keys($formato->evaluar($domSolicitud(['detalle' => ['x' => str_repeat('a', 5000)]]))->campos), 'detalle de más de 4 KB');
    $catalogo = new ReglaAccionEnCatalogo(new CatalogoAcciones(['pacientes' => ['PACIENTE_REGISTRADO']]));
    igual([null, 'DATOS_INVALIDOS'], [$codigo($catalogo, $domSolicitud()), $codigo($catalogo, $domSolicitud(['accion' => 'ROL_ASIGNADO']))], 'catálogo');
    igual('DATOS_INVALIDOS', $codigo(new ReglaResultadoValido(), $domSolicitud(['resultado' => 'ok'])), 'resultado');
    $fecha = new ReglaFechaNoFutura(new RelojFijo('2026-10-21T12:00:00-06:00'), 5);
    igual([null, 'DATOS_INVALIDOS', 'DATOS_INVALIDOS'], [$codigo($fecha, $domSolicitud(['ocurrido_en' => '2026-10-21T12:05:00-06:00'])),
        $codigo($fecha, $domSolicitud(['ocurrido_en' => '2026-10-21T12:05:01-06:00'])), $codigo($fecha, $domSolicitud(['ocurrido_en' => '2026-10-21']))],
        'fecha: +5:00 / +5:01 / sin hora ni zona');
    foreach ([['password' => 'x'], ['sesion' => ['Token' => 't']], ['diagnostico' => 'J45']] as $detalle) {
        igual('DATOS_INVALIDOS', $codigo(new ReglaSinDatosProhibidos(), $domSolicitud(['detalle' => $detalle])), json_encode($detalle));
    }
});

prueba('DOM-03', 'Sello: depende del sello anterior, de la secuencia y del contenido; es determinista', function () use ($domMov): void {
    $m = $domMov();
    $s = Sello::calcular(Sello::GENESIS, 1, $m);
    igual($s, Sello::calcular(Sello::GENESIS, 1, $domMov()), 'determinista');
    afirmar($s !== Sello::calcular(str_repeat('1', 64), 1, $m), 'no depende del anterior');
    afirmar($s !== Sello::calcular(Sello::GENESIS, 2, $m), 'no depende de la secuencia');
    afirmar($s !== Sello::calcular(Sello::GENESIS, 1, $domMov(['usuario' => 'u-otro'])), 'no depende del contenido');
});

prueba('DOM-04', 'FiltroRangoFechas: obligatorio; «hasta» incluye el día entero en hora de Guatemala; el SQL lleva los límites en UTC como parámetros', function () use ($domMov): void {
    $f = new FiltroRangoFechas('America/Guatemala');
    $p = ['desde' => '2026-10-20', 'hasta' => '2026-10-20'];
    afirmar($f->obligatorio(), 'el rango no es obligatorio');
    igual([[], ['desde' => 'Desde no puede ser posterior a hasta.']], [$f->validar($p), $f->validar(['desde' => '2026-10-21', 'hasta' => '2026-10-20'])], 'validar');
    igual([true, false, false], [$f->coincide($domMov(['ocurrido_en' => '2026-10-20T23:59:59-06:00']), $p),
        $f->coincide($domMov(['ocurrido_en' => '2026-10-21T00:00:00-06:00']), $p), $f->coincide($domMov(['ocurrido_en' => '2026-10-19T23:59:59-06:00']), $p)],
        '23:59:59 del 20 sí; 00:00 del 21 no; 23:59:59 del 19 no');
    igual(['ocurrido_en >= :desde AND ocurrido_en < :hasta_exclusivo', ['desde' => '2026-10-20 06:00:00', 'hasta_exclusivo' => '2026-10-21 06:00:00']],
        $f->sql($p), 'fragmento y parámetros');
});

prueba('DOM-05', 'Filtros por campo: el fragmento SQL solo tiene marcadores; un valor malicioso queda como parámetro', function (): void {
    $malicioso = "u-ana' OR '1'='1";
    foreach ([[new FiltroUsuario(), 'usuario', 'usuario_id = :usuario'], [new FiltroModulo(), 'modulo', 'modulo = :modulo']] as [$filtro, $nombre, $esperado]) {
        [$sql, $parametros] = $filtro->sql([$nombre => $malicioso]);
        igual([$esperado, [$nombre => $malicioso]], [$sql, $parametros], $nombre);
    }
    igual([[], ['resultado' => 'Debe ser exito, denegado o error.']], [(new FiltroResultado())->validar(['resultado' => 'denegado']),
        (new FiltroResultado())->validar(['resultado' => 'todos'])], 'resultado');
});

prueba('DOM-06', 'ConsultaMovimientos: el alcance de hospital excluye otros hospitales; la red los incluye; el cursor excluye lo ya entregado', function (): void {
    $m = static fn (string $hospital, int $id, string $fecha): Movimiento => Movimiento::reconstituir($id, 'svc-pacientes', "e$id", $hospital, 'u', 'pacientes',
        'PACIENTE_REGISTRADO', new DateTimeImmutable($fecha), 'exito', null, null, [], $id, str_repeat('0', 64));
    $norte = $m(Escenario::H_NORTE, 1, '2026-10-21T10:00:00Z');
    igual([false, true], [(new ConsultaMovimientos(Escenario::H_ORIENTE, false, [], [], 51))->coincide($norte),
        (new ConsultaMovimientos(null, true, [], [], 51))->coincide($norte)], 'hospital / red');
    $cursor = new ConsultaMovimientos(null, true, [], [], 51, [new DateTimeImmutable('2026-10-21T10:00:00Z'), 5]);
    igual([true, false, false], [$cursor->coincide($m('h', 4, '2026-10-21T10:00:00Z')), $cursor->coincide($m('h', 5, '2026-10-21T10:00:00Z')),
        $cursor->coincide($m('h', 3, '2026-10-21T10:00:01Z'))], 'misma hora id menor sí; el mismo no; más reciente no');
});

<?php

declare(strict_types=1);

/*
 * Semana 7 — Pruebas UNITARIAS de las piezas que el refactor sacó de ConsultarMovimientos.
 * Antes solo se podían probar a través del caso de uso completo (con transacción y bitácora).
 */

use Auditoria\Aplicacion\Servicios\AutorizacionLectura;
use Auditoria\Aplicacion\Servicios\CursorPaginacion;
use Auditoria\Dominio\Excepciones\ConsultaInvalida;
use Auditoria\Dominio\Filtros\CatalogoFiltros;
use Auditoria\Dominio\Filtros\FiltroRangoFechas;
use Auditoria\Dominio\Filtros\FiltroUsuario;
use Auditoria\Dominio\Modelo\Alcance;
use Auditoria\Dominio\Modelo\Movimiento;
use Auditoria\Dominio\Modelo\SolicitudRegistro;
use Auditoria\Persistencia\Configuracion\PoliticaAccesoEstatica;
use Auditoria\Pruebas\Dobles\Escenario;

$piezaMov = static fn (string $hospital, int $id = 7): Movimiento =>
    Movimiento::desdeSolicitud(new SolicitudRegistro('svc-pacientes', $hospital, Escenario::mov(['hospital' => $hospital])))->conId($id);

prueba('REF-04', 'Alcance: «*» es la red (ve todo); un hospital solo se incluye a sí mismo; tenant y etiqueta para la política y la meta-auditoría', function () use ($piezaMov): void {
    $red = Alcance::desdeTenant('*');
    $oriente = Alcance::desdeTenant(Escenario::H_ORIENTE);
    igual([true, null, '*', 'red'], [$red->esRed(), $red->hospital, $red->tenant(), $red->etiqueta()], 'red');
    igual([false, Escenario::H_ORIENTE, Escenario::H_ORIENTE, 'hospital'],
        [$oriente->esRed(), $oriente->hospital, $oriente->tenant(), $oriente->etiqueta()], 'hospital');
    igual([true, false, true, true], [$oriente->incluye($piezaMov(Escenario::H_ORIENTE)), $oriente->incluye($piezaMov(Escenario::H_NORTE)),
        $red->incluye($piezaMov(Escenario::H_ORIENTE)), $red->incluye($piezaMov(Escenario::H_NORTE))], 'incluye');
});

prueba('REF-05', 'CatalogoFiltros: rechaza lo que ningún filtro reclama, exige el rango aunque no venga y devuelve solo los filtros activos', function (): void {
    $catalogo = new CatalogoFiltros([new FiltroRangoFechas(), new FiltroUsuario()]);
    $error = static function (array $p) use ($catalogo): array {
        try {
            $catalogo->interpretar($p);

            return [];
        } catch (ConsultaInvalida $e) {
            return [$e->codigo, array_keys($e->campos)];
        }
    };
    igual([ConsultaInvalida::FILTRO_NO_SOPORTADO, ['modulo']], $error(['desde' => '2026-10-01', 'hasta' => '2026-10-02', 'modulo' => 'x']),
        'un filtro que este catálogo no tiene');
    igual([ConsultaInvalida::DATOS_INVALIDOS, ['desde']], $error([]), 'sin rango');
    igual([ConsultaInvalida::DATOS_INVALIDOS, ['desde']], $error(['desde' => '2026-10-05', 'hasta' => '2026-10-01']), 'rango invertido');
    $activos = static fn (array $p): array => array_map(static fn (object $f): string => (new ReflectionClass($f))->getShortName(),
        $catalogo->interpretar($p));
    igual(['FiltroRangoFechas'], $activos(['desde' => '2026-10-01', 'hasta' => '2026-10-02']), 'solo el rango');
    igual(['FiltroRangoFechas', 'FiltroUsuario'], $activos(['desde' => '2026-10-01', 'hasta' => '2026-10-02', 'usuario' => 'u-ana']), 'rango y usuario');
});

prueba('REF-06', 'CursorPaginacion ida y vuelta, cursores inválidos → null; AutorizacionLectura pide consultar_red solo para la red', function () use ($piezaMov): void {
    $m = $piezaMov(Escenario::H_ORIENTE, 42);
    [$fecha, $id] = CursorPaginacion::leer(CursorPaginacion::de($m));
    igual([$m->ocurridoEn->format(DATE_ATOM), 42], [$fecha->format(DATE_ATOM), $id], 'ida y vuelta');
    // DEF-07-01 (hallado por esta prueba en la semana 7, corregido en la semana 15 con UNI-08): la fecha imposible da null.
    foreach (['%%%', base64_encode('no-es-un-cursor'), base64_encode('2026-10-21T15:15:00Z|x'), base64_encode('2026-13-45T99:00:00Z|1'), ''] as $malo) {
        igual(null, CursorPaginacion::leer($malo), 'inválido: ' . $malo);
    }
    $autorizacion = new AutorizacionLectura(new PoliticaAccesoEstatica([
        'auditor|hosp-oriente' => [AutorizacionLectura::PERMISO],
        'auditor|*' => [AutorizacionLectura::PERMISO],          // permiso de hospital pedido «para la red»: no alcanza
        'superadmin|*' => [AutorizacionLectura::PERMISO_RED],
    ]));
    igual([true, false, true, false], [
        $autorizacion->permite('auditor', Alcance::desdeTenant(Escenario::H_ORIENTE)),
        $autorizacion->permite('auditor', Alcance::desdeTenant('*')),
        $autorizacion->permite('superadmin', Alcance::desdeTenant('*')),
        $autorizacion->permite('superadmin', Alcance::desdeTenant(Escenario::H_ORIENTE)),
    ], 'permiso según el alcance');
});

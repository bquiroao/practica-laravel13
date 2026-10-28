<?php

declare(strict_types=1);

/*
 * Semana 15 — Pruebas UNITARIAS de las reglas de audit logs inmutables, usuario, módulo, acción, tenant y filtros por
 * rango de fechas. Cada una prueba UNA clase del Dominio (o de Aplicación sin E/S), sin kernel, sin transacción y sin base.
 * Trazabilidad: matriz-rf-rnf-pruebas.md (semana 15).
 */

use Auditoria\Aplicacion\Servicios\CursorPaginacion;
use Auditoria\Dominio\Filtros\FiltroRangoFechas;
use Auditoria\Dominio\Modelo\Alcance;
use Auditoria\Dominio\Modelo\CatalogoAcciones;
use Auditoria\Dominio\Modelo\Movimiento;
use Auditoria\Dominio\Modelo\Rechazo;
use Auditoria\Dominio\Modelo\Sello;
use Auditoria\Dominio\Modelo\SolicitudRegistro;
use Auditoria\Dominio\Reglas\ReglaAccionEnCatalogo;
use Auditoria\Dominio\Reglas\ReglaCamposObligatorios;
use Auditoria\Dominio\Reglas\ReglaFormatoCampos;
use Auditoria\Dominio\Reglas\ReglaModuloDelEmisor;
use Auditoria\Dominio\Reglas\ReglaTenantDelEmisor;
use Auditoria\Pruebas\Dobles\Escenario;

$uniSolicitud = static fn (array $cambios = [], string $emisor = 'svc-pacientes', ?string $hospitalToken = Escenario::H_ORIENTE): SolicitudRegistro =>
    new SolicitudRegistro($emisor, $hospitalToken, Escenario::mov($cambios));
$uniCampos = static fn (?Rechazo $r): ?array => $r === null ? null : array_keys($r->campos);
$uniMov = static fn (array $cambios = []): Movimiento => Movimiento::desdeSolicitud(new SolicitudRegistro('svc-pacientes', Escenario::H_ORIENTE, Escenario::mov($cambios)));

prueba('UNI-01', '[inmutable] sellar o asignar id devuelve OTRA instancia; el original no cambia (R-02)', function () use ($uniMov): void {
    $m = $uniMov();
    $sellado = $m->sellado(1, str_repeat('a', 64));
    $conId = $sellado->conId(7);
    afirmar($sellado !== $m && $conId !== $sellado, 'debe devolver instancias nuevas');
    igual([0, '', null], [$m->secuencia, $m->sello, $m->id], 'el original sigue sin secuencia, sello ni id');
    igual([1, null, 7], [$sellado->secuencia, $sellado->id, $conId->id], 'cada paso deja la anterior intacta');
    lanza(Error::class, function () use ($m): void {
        $m->usuario = 'otro';
    }, 'escribir una propiedad readonly');
});

prueba('UNI-02', '[inmutable] el sello depende de CADA campo del contenido, de la secuencia y del sello anterior (R-03)', function () use ($uniMov): void {
    $base = Sello::calcular(Sello::GENESIS, 1, $uniMov());
    igual(64, strlen($base), 'sha256 en hexadecimal');
    igual($base, Sello::calcular(Sello::GENESIS, 1, $uniMov()), 'determinista');
    foreach (['usuario' => 'u-otro', 'modulo' => 'rbac', 'accion' => 'PACIENTE_BUSCADO', 'hospital' => Escenario::H_NORTE, 'resultado' => 'denegado',
        'ocurrido_en' => '2026-10-21T09:15:01-06:00', 'recurso' => 'paciente:P-000002', 'detalle' => ['codigo' => 'P-000002']] as $campo => $valor) {
        afirmar(Sello::calcular(Sello::GENESIS, 1, $uniMov([$campo => $valor])) !== $base, "cambiar «{$campo}» debe cambiar el sello");
    }
    afirmar(Sello::calcular(Sello::GENESIS, 2, $uniMov()) !== $base, 'otra secuencia');
    afirmar(Sello::calcular(str_repeat('f', 64), 1, $uniMov()) !== $base, 'otro sello anterior');
});

prueba('UNI-03', '[usuario] usuario obligatorio y de 1 a 64 caracteres; 64 se acepta, 65 no (R-01, RF-03)', function () use ($uniSolicitud, $uniCampos): void {
    $obligatorios = new ReglaCamposObligatorios();
    $formato = new ReglaFormatoCampos();
    igual(['usuario'], $uniCampos($obligatorios->evaluar($uniSolicitud(['usuario' => '']))), 'vacío');
    $sinUsuario = Escenario::mov();
    unset($sinUsuario['usuario']);
    igual(['usuario'], $uniCampos($obligatorios->evaluar(new SolicitudRegistro('svc-pacientes', Escenario::H_ORIENTE, $sinUsuario))), 'ausente');
    igual(null, $formato->evaluar($uniSolicitud(['usuario' => str_repeat('u', 64)])), '64 caracteres');
    igual(['usuario'], $uniCampos($formato->evaluar($uniSolicitud(['usuario' => str_repeat('u', 65)]))), '65 caracteres');
});

prueba('UNI-04', '[módulo] cada cuenta de servicio solo registra SU módulo; una cuenta no listada no registra nada (fail-closed)', function () use ($uniSolicitud): void {
    $regla = new ReglaModuloDelEmisor(['svc-pacientes' => 'pacientes', 'svc-rbac' => 'rbac']);
    igual(null, $regla->evaluar($uniSolicitud()), 'pacientes por svc-pacientes');
    igual('MODULO_NO_PERMITIDO', $regla->evaluar($uniSolicitud(['modulo' => 'rbac']))?->codigo, 'rbac por svc-pacientes');
    igual('MODULO_NO_PERMITIDO', $regla->evaluar($uniSolicitud([], 'svc-desconocido'))?->codigo, 'cuenta no listada');
    igual(Rechazo::PROHIBIDO, $regla->evaluar($uniSolicitud(['modulo' => 'rbac']))?->categoria, 'es un rechazo de permiso (403), no de datos');
});

prueba('UNI-05', '[acción] la acción debe estar en el catálogo DE SU módulo: una acción válida de otro módulo se rechaza (RF-03)', function () use ($uniSolicitud, $uniCampos): void {
    $regla = new ReglaAccionEnCatalogo(new CatalogoAcciones(['pacientes' => ['PACIENTE_REGISTRADO', 'PACIENTE_CONSULTADO'], 'rbac' => ['ROL_ASIGNADO']]));
    igual(null, $regla->evaluar($uniSolicitud()), 'PACIENTE_REGISTRADO en pacientes');
    igual(['accion'], $uniCampos($regla->evaluar($uniSolicitud(['accion' => 'ROL_ASIGNADO']))), 'acción de rbac en pacientes');
    igual(['accion'], $uniCampos($regla->evaluar($uniSolicitud(['accion' => 'paciente_registrado']))), 'minúsculas: no se normaliza');
    igual(['accion'], $uniCampos($regla->evaluar($uniSolicitud(['modulo' => 'citas', 'accion' => 'CITA_CREADA']))), 'módulo sin catálogo');
});

prueba('UNI-06', '[tenant] el hospital del cuerpo debe ser el del token; el alcance de un hospital solo incluye lo suyo (R-05)', function () use ($uniSolicitud, $uniMov): void {
    $regla = new ReglaTenantDelEmisor();
    igual(null, $regla->evaluar($uniSolicitud()), 'mismo hospital');
    igual('TENANT_NO_COINCIDE', $regla->evaluar($uniSolicitud(['hospital' => Escenario::H_NORTE]))?->codigo, 'el cuerpo declara otro hospital');
    igual('TENANT_NO_COINCIDE', $regla->evaluar($uniSolicitud([], 'svc-pacientes', '*'))?->codigo, 'un emisor con token de red no registra en un hospital');
    $oriente = Alcance::desdeTenant(Escenario::H_ORIENTE);
    igual([true, false, true], [$oriente->incluye($uniMov()), $oriente->incluye($uniMov(['hospital' => Escenario::H_NORTE])),
        Alcance::desdeTenant('*')->incluye($uniMov(['hospital' => Escenario::H_NORTE]))], 'Alcance::incluye');
});

prueba('UNI-07', '[rango de fechas] hora de Guatemala; «hasta» incluye hasta 23:59:59 local; el día siguiente 00:00 queda fuera (R-06)', function () use ($uniMov): void {
    $f = new FiltroRangoFechas('America/Guatemala');
    $p = ['desde' => '2026-10-21', 'hasta' => '2026-10-21'];
    $en = static fn (string $cuando): bool => $f->coincide($uniMov(['ocurrido_en' => $cuando]), $p);
    igual([true, true, false, false], [$en('2026-10-21T00:00:00-06:00'), $en('2026-10-21T23:59:59-06:00'), $en('2026-10-22T00:00:00-06:00'),
        $en('2026-10-20T23:59:59-06:00')], 'límites del día local');
    igual(true, $en('2026-10-22T05:59:59Z'), 'en UTC, 05:59:59 del 22 sigue siendo el 21 en Guatemala');
    igual([[], ['desde'], ['desde'], ['desde']], [array_keys($f->validar($p)), array_keys($f->validar(['desde' => '2026-10-22', 'hasta' => '2026-10-21'])),
        array_keys($f->validar(['desde' => '2026-02-30', 'hasta' => '2026-03-01'])), array_keys($f->validar(['desde' => '21/10/2026', 'hasta' => '2026-10-21']))],
        'válido · invertido · fecha imposible · formato de la UI');
});

prueba('UNI-08', '[rango de fechas · DEF-07-01] el cursor rechaza fechas imposibles (mes 13, hora 99) en lugar de desbordarlas', function (): void {
    $cursor = static fn (string $t): string => rtrim(strtr(base64_encode($t), '+/', '-_'), '=');
    igual(null, CursorPaginacion::leer($cursor('2026-13-45T99:00:00Z|1')), 'mes 13, día 45, hora 99');
    igual(null, CursorPaginacion::leer($cursor('2026-02-30T10:00:00Z|1')), '30 de febrero');
    $valido = CursorPaginacion::leer($cursor('2026-10-21T15:15:00Z|2'));
    igual(['2026-10-21T15:15:00+00:00', 2], [$valido[0]->format(DATE_ATOM), $valido[1]], 'un cursor válido sigue funcionando');
});

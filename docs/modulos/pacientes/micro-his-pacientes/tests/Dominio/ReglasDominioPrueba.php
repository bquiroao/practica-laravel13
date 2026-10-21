<?php

declare(strict_types=1);

use Pacientes\Dominio\Excepciones\DatosInvalidos;
use Pacientes\Dominio\Excepciones\ErrorDominio;
use Pacientes\Dominio\Modelo\CriterioBusqueda;
use Pacientes\Dominio\Modelo\DatosPaciente;
use Pacientes\Dominio\Modelo\Dpi;
use Pacientes\Dominio\Modelo\Paciente;
use Pacientes\Dominio\Modelo\Pasaporte;
use Pacientes\Dominio\Servicios\PoliticaDuplicados;
use Pacientes\Dominio\Servicios\PoliticaEdicion;
use Pacientes\Pruebas\Dobles\Escenario;
use Pacientes\Pruebas\Dobles\IndiceDuplicadosEnMemoria;
use Pacientes\Pruebas\Dobles\RepositorioPacientesEnMemoria;

$hoy = new DateTimeImmutable('2026-10-21');
$paciente = static fn (string $nombres = 'Ana Lucía', ?string $dpi = Escenario::DPI_ANA, string $nacimiento = '1985-03-14'): Paciente =>
    Paciente::desdeDatos(Escenario::H_ORIENTE, new DatosPaciente($nombres, 'López Pérez', $nacimiento, 'female', null,
        $dpi === null ? null : 'DPI', $dpi), $hoy);

prueba('DOM-01', 'Datos válidos: paciente activo, sin código ni versión, con DPI', function () use ($paciente): void {
    $p = $paciente();
    igual(null, $p->codigo, 'código');
    igual(0, $p->version, 'versión');
    igual(Paciente::ACTIVO, $p->estado, 'estado');
    igual('DPI', $p->identificador?->tipo(), 'documento');
    igual(false, $p->identidadProvisional(), 'identidad provisional');
});

prueba('DOM-02', 'R-01: nombre vacío, fecha futura, sexo, teléfono y DPI inválidos → DATOS_INVALIDOS con los 5 campos', function () use ($hoy): void {
    $e = lanza(DatosInvalidos::class, fn () => Paciente::desdeDatos(Escenario::H_ORIENTE,
        new DatosPaciente('  ', 'López', '2030-01-01', 'x', 'abc', 'DPI', '123'), $hoy), 'datos inválidos');
    igual(['nombres', 'nacimiento', 'sexo', 'telefono', 'documento'], array_keys($e->detalle['campos']), 'campos');
    lanza(DatosInvalidos::class, fn () => Paciente::desdeDatos(Escenario::H_ORIENTE,
        new DatosPaciente('Ana', 'López', '1985-02-30', 'female'), $hoy), '30 de febrero');
});

prueba('DOM-03', 'DPI: 13 dígitos y departamento 01–22; con espacios o guiones es el mismo; se enmascara', function (): void {
    lanza(InvalidArgumentException::class, fn () => Dpi::desde('1234567899901'), 'departamento 99');
    lanza(InvalidArgumentException::class, fn () => Dpi::desde('12345'), 'longitud');
    igual(Dpi::desde(Escenario::DPI_ANA)->canonico(), Dpi::desde('2547 89632-0101')->canonico(), 'canónico');
    igual('*********0101', Dpi::desde(Escenario::DPI_ANA)->enmascarado(), 'enmascarado');
});

prueba('DOM-04', 'Contrato Identificador (semana 2): canónico no vacío, igual/distinto, sin el número en el enmascarado', function (): void {
    $casos = [
        [Dpi::desde(Escenario::DPI_ANA), Dpi::desde('2547-89632-0101'), Dpi::desde(Escenario::DPI_LUIS), Escenario::DPI_ANA],
        [Pasaporte::desde('X1234567', 'GTM'), Pasaporte::desde('x123 4567', 'gtm'), Pasaporte::desde('X1234567', 'MEX'), 'X1234567'],
    ];
    foreach ($casos as [$a, $mismo, $otro, $numero]) {
        afirmar($a->canonico() !== '', $a::class . ': canónico vacío');
        igual($a->canonico(), $mismo->canonico(), $a::class . ' mismo documento');
        afirmar($a->canonico() !== $otro->canonico(), $a::class . ': documentos distintos con el mismo canónico');
        afirmar(!str_contains($a->enmascarado(), $numero), $a::class . ': el enmascarado muestra el número');
    }
});

prueba('DOM-05', 'R-10: criterio con menos de 3 letras → CRITERIO_INSUFICIENTE; fecha mal escrita → DATOS_INVALIDOS', function (): void {
    $e = lanza(ErrorDominio::class, fn () => CriterioBusqueda::desde(nombre: 'pé'), 'criterio corto');
    igual('CRITERIO_INSUFICIENTE', $e->codigo, 'código');
    lanza(DatosInvalidos::class, fn () => CriterioBusqueda::desde(nombre: 'perez', nacimiento: '14/03/1985'), 'fecha');
    igual(CriterioBusqueda::POR_CODIGO, CriterioBusqueda::desde(codigo: 'p-000001', nombre: 'x')->tipo, 'el código tiene prioridad');
    igual('nombre(5 letras)+nacimiento', CriterioBusqueda::desde(nombre: 'PÉREZ', nacimiento: '1985')->resumenAuditable(), 'resumen sin datos');
});

prueba('DOM-06', 'R-02/R-03: documento existente bloquea; coincidencia exige confirmación, permiso y motivo', function () use ($paciente): void {
    $repo = new RepositorioPacientesEnMemoria();
    $repo->registrar($paciente());
    $politica = new PoliticaDuplicados(new IndiceDuplicadosEnMemoria($repo));

    $e = lanza(ErrorDominio::class, fn () => $politica->verificarRegistro($paciente('Otra'), false, false, ''), 'mismo DPI');
    igual(['PACIENTE_EXISTENTE', 'P-000001', '*********0101'], [$e->codigo, $e->detalle['codigo'], $e->detalle['identificador']], 'bloqueo');

    $parecida = $paciente('ANA  lucia', null);
    igual('POSIBLE_DUPLICADO', lanza(ErrorDominio::class, fn () => $politica->verificarRegistro($parecida, false, true, 'x'), 'sin confirmar')->codigo, 'advertencia');
    igual('ACCESO_DENEGADO', lanza(ErrorDominio::class, fn () => $politica->verificarRegistro($parecida, true, false, 'x'), 'sin permiso')->codigo, 'permiso');
    igual('MOTIVO_REQUERIDO', lanza(ErrorDominio::class, fn () => $politica->verificarRegistro($parecida, true, true, ' '), 'sin motivo')->codigo, 'motivo');
    igual(['P-000001'], $politica->verificarRegistro($parecida, true, true, 'Homónima'), 'confirmado');
});

prueba('DOM-07', 'R-08/R-07: fallecido solo con supervisor; cambiar identidad exige motivo; el teléfono no', function () use ($paciente, $hoy): void {
    $politica = new PoliticaEdicion();
    $actual = $paciente()->conCodigo('P-000001', 1);
    $soloTelefono = $actual->conDatosDe(Paciente::desdeDatos(Escenario::H_ORIENTE,
        new DatosPaciente('Ana Lucía', 'López Pérez', '1985-03-14', 'female', '+502 4444 0000', 'DPI', Escenario::DPI_ANA), $hoy));

    $politica->verificar($actual, $soloTelefono, false, ''); // no lanza
    igual('REQUIERE_SUPERVISOR', lanza(ErrorDominio::class, fn () => $politica->verificar($actual->conEstado(Paciente::FALLECIDO), $soloTelefono, false, ''), 'fallecido')->codigo, 'estado');
    $politica->verificar($actual->conEstado(Paciente::FALLECIDO), $soloTelefono, true, ''); // supervisor: no lanza
    igual('MOTIVO_REQUERIDO', lanza(ErrorDominio::class, fn () => $politica->verificar($actual, $paciente('Ana María'), false, ''), 'identidad')->codigo, 'motivo');
});

prueba('DOM-08', 'Cambio de identidad: tildes y mayúsculas no cuentan; fecha y documento sí', function () use ($paciente): void {
    $base = $paciente();
    igual(false, $paciente('ANA LUCIA')->cambiaIdentidadRespectoDe($base), 'solo tildes/mayúsculas');
    igual(true, $paciente(nacimiento: '1985-03-15')->cambiaIdentidadRespectoDe($base), 'fecha');
    igual(true, $paciente(dpi: Escenario::DPI_NUEVO)->cambiaIdentidadRespectoDe($base), 'documento');
    igual(true, $paciente(dpi: null)->cambiaIdentidadRespectoDe($base), 'quitar el documento');
});

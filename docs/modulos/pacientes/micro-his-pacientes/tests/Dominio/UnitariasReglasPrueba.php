<?php

declare(strict_types=1);

/*
 * Semana 15 · Pruebas UNITARIAS de las reglas de la ficha: identidad, datos demográficos, búsqueda,
 * edición, duplicados y confidencialidad. Cada unidad se prueba AISLADA (sin caso de uso ni HTTP),
 * con valores límite. Matriz: docs/modulos/pacientes/semana-15/matriz-rf-rnf-pruebas.md
 */

use Pacientes\Dominio\Excepciones\DatosInvalidos;
use Pacientes\Dominio\Excepciones\ErrorDominio;
use Pacientes\Dominio\Modelo\CriterioBusqueda;
use Pacientes\Dominio\Modelo\DatosPaciente;
use Pacientes\Dominio\Modelo\Dpi;
use Pacientes\Dominio\Modelo\FabricaIdentificador;
use Pacientes\Dominio\Modelo\Paciente;
use Pacientes\Dominio\Modelo\Pasaporte;
use Pacientes\Dominio\Servicios\PoliticaConfidencialidad;
use Pacientes\Dominio\Servicios\PoliticaDuplicados;
use Pacientes\Dominio\Servicios\PoliticaEdicion;
use Pacientes\Persistencia\EnMemoria\IndiceDuplicadosEnMemoria;
use Pacientes\Persistencia\EnMemoria\RepositorioPacientesEnMemoria;
use Pacientes\Pruebas\Dobles\Escenario;

$uniHoy = new DateTimeImmutable('2026-10-21');

/** Datos de Ana con los cambios indicados (argumentos con nombre de DatosPaciente). */
$uniDatos = static fn (array $cambios = []): DatosPaciente => new DatosPaciente(...array_merge([
    'nombres' => 'Ana Lucía', 'apellidos' => 'López Pérez', 'nacimiento' => '1985-03-14', 'sexo' => 'female',
    'telefono' => '+502 5555 1234', 'tipoDocumento' => 'DPI', 'numeroDocumento' => Escenario::DPI_ANA,
], $cambios));

$uniFicha = static fn (array $cambios = []): Paciente => Paciente::desdeDatos(Escenario::H_ORIENTE, $uniDatos($cambios), $uniHoy);

/** Campos que rechaza R-01 para esos datos ([] = válidos). */
$uniCampos = static function (array $cambios) use ($uniFicha): array {
    try {
        $uniFicha($cambios);
        return [];
    } catch (DatosInvalidos $e) {
        return array_keys($e->detalle['campos']);
    }
};

/** Código del ErrorDominio que lanza la operación, o null si no lanza. */
$uniCodigo = static function (callable $operacion): ?string {
    try {
        $operacion();
        return null;
    } catch (ErrorDominio $e) {
        return $e->codigo;
    }
};

/** Ficha ya guardada (con código), opcionalmente protegida o en otro estado. */
$uniGuardada = static fn (bool $protegida = false, string $estado = Paciente::ACTIVO, ?string $dpi = Escenario::DPI_ANA): Paciente =>
    Paciente::reconstituir('P-000001', Escenario::H_ORIENTE, 'Ana Lucía', 'López Pérez', new DateTimeImmutable('1985-03-14'), 'female',
        '+502 5555 1234', $dpi === null ? null : Dpi::desde($dpi), $estado, 1, $protegida);

prueba('UNI-01', 'Identidad · Dpi: límites 12/13/14 dígitos y departamento 00/01/22/23; separadores; enmascarado y canónico', function (): void {
    $valido = static function (string $numero): bool {
        try {
            Dpi::desde($numero);
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    };
    igual([false, true, false], [$valido('254789632010'), $valido('2547896320101'), $valido('25478963201011')], 'longitud 12 / 13 / 14');
    igual([false, true, true, false], [$valido('2547896320001'), $valido('2547896320101'), $valido('2547896322201'), $valido('2547896322301')],
        'departamento 00 / 01 / 22 / 23');
    igual(false, $valido('25478963201O1'), 'una letra O en lugar de un cero');
    igual(Dpi::desde(Escenario::DPI_ANA)->canonico(), Dpi::desde(' 2547-89632 0101 ')->canonico(), 'con guiones y espacios es el mismo DPI');
    igual('GTM-RENAP|DPI|' . Escenario::DPI_ANA, Dpi::desde(Escenario::DPI_ANA)->canonico(), 'canónico = emisor|tipo|número');
    igual('*********0101', Dpi::desde(Escenario::DPI_ANA)->enmascarado(), 'solo los últimos 4 en claro');
});

prueba('UNI-02', 'Identidad · Pasaporte: límites 5/6/20/21; país de 3 letras; el mismo número de otro país es otra identidad', function (): void {
    $valido = static function (string $numero, string $pais = 'GTM'): bool {
        try {
            Pasaporte::desde($numero, $pais);
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    };
    igual([false, true, true, false], [$valido('AB123'), $valido('AB1234'), $valido(str_repeat('A', 20)), $valido(str_repeat('A', 21))],
        'longitud 5 / 6 / 20 / 21');
    igual([false, true, false], [$valido('AB1234', 'GT'), $valido('AB1234', 'gtm'), $valido('AB1234', 'GTMX')], 'país de 2 / 3 / 4 letras');
    igual('AB1234', Pasaporte::desde('ab-12 34', 'gtm')->numero(), 'mayúsculas y sin separadores');
    afirmar(Pasaporte::desde('AB1234', 'GTM')->canonico() !== Pasaporte::desde('AB1234', 'MEX')->canonico(),
        'mismo número, distinto país: canónicos iguales');
    igual('**1234', Pasaporte::desde('AB1234', 'GTM')->enmascarado(), 'enmascarado');
});

prueba('UNI-03', 'Identidad · FabricaIdentificador: sin tipo ni número = provisional (RF-04); número sin tipo y tipo no admitido se rechazan', function (): void {
    igual(null, FabricaIdentificador::crear(null, null), 'sin documento');
    igual(null, FabricaIdentificador::crear('  ', '  '), 'solo espacios = sin documento');
    $mensaje = static function (?string $tipo, ?string $numero): string {
        try {
            FabricaIdentificador::crear($tipo, $numero, 'GTM');
            return 'aceptado';
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }
    };
    igual('Indique el tipo de documento.', $mensaje(null, Escenario::DPI_ANA), 'número sin tipo');
    igual('Tipo de documento no admitido: CEDULA.', $mensaje('cedula', '123'), 'tipo fuera del catálogo');
    igual('DPI', FabricaIdentificador::crear('dpi', Escenario::DPI_ANA)?->tipo(), 'el tipo no distingue mayúsculas');
    igual('PASAPORTE', FabricaIdentificador::crear('Pasaporte', 'AB1234', 'GTM')?->tipo(), 'pasaporte');
});

prueba('UNI-04', 'Demografía · R-01 con valores límite: nombres 100/101 (multibyte), nacimiento hoy/mañana/29-feb, sexo, teléfono 7/8/15/16', function () use ($uniCampos, $uniFicha): void {
    igual([], $uniCampos(['nombres' => str_repeat('ñ', 100)]), '100 «ñ» (200 bytes) siguen siendo 100 caracteres');
    igual(['nombres'], $uniCampos(['nombres' => str_repeat('ñ', 101)]), '101 caracteres');
    igual(['nombres', 'apellidos'], $uniCampos(['nombres' => '   ', 'apellidos' => '']), 'vacíos y solo espacios');
    igual([], $uniCampos(['nacimiento' => '2026-10-21']), 'nacido hoy');
    igual(['nacimiento'], $uniCampos(['nacimiento' => '2026-10-22']), 'nacido mañana');
    igual([[], ['nacimiento']], [$uniCampos(['nacimiento' => '2024-02-29']), $uniCampos(['nacimiento' => '2025-02-29'])], '29 de febrero bisiesto / no bisiesto');
    igual(['nacimiento'], $uniCampos(['nacimiento' => '1985-3-14']), 'sin ceros a la izquierda');
    foreach (Paciente::SEXOS as $sexo) {
        igual([], $uniCampos(['sexo' => $sexo]), "sexo $sexo");
    }
    igual(['sexo'], $uniCampos(['sexo' => 'Female']), 'el catálogo distingue mayúsculas');
    igual([['telefono'], [], [], ['telefono']], [$uniCampos(['telefono' => '1234567']), $uniCampos(['telefono' => '12345678']),
        $uniCampos(['telefono' => str_repeat('1', 15)]), $uniCampos(['telefono' => str_repeat('1', 16)])], 'teléfono de 7 / 8 / 15 / 16 dígitos');
    $p = $uniFicha(['nombres' => '  Ana Lucía  ', 'telefono' => '  ']);
    igual(['Ana Lucía', null], [$p->nombres, $p->telefono], 'se recortan espacios; teléfono en blanco = sin teléfono');
    igual(['nombres', 'nacimiento', 'sexo', 'telefono', 'documento'],
        $uniCampos(['nombres' => '', 'nacimiento' => 'ayer', 'sexo' => 'x', 'telefono' => 'abc', 'numeroDocumento' => '1']),
        'informa TODOS los campos inválidos a la vez');
});

prueba('UNI-05', 'Edición · R-07: qué es «cambiar la identidad» (nombre normalizado, nacimiento, documento); el teléfono o una tilde no lo son', function () use ($uniFicha): void {
    $ana = $uniFicha();
    $cambia = static fn (array $cambios): bool => $ana->conDatosDe($uniFicha($cambios))->cambiaIdentidadRespectoDe($ana);
    igual(false, $cambia([]), 'mismos datos');
    igual(false, $cambia(['telefono' => '+502 4444 0000']), 'solo el teléfono');
    igual(false, $cambia(['nombres' => 'ANA  LUCIA', 'apellidos' => 'lopez perez']), 'mayúsculas, tildes y espacios dobles');
    igual(true, $cambia(['nombres' => 'Ana María']), 'otro nombre');
    igual(true, $cambia(['nacimiento' => '1985-03-15']), 'un día de diferencia en el nacimiento');
    igual(true, $cambia(['numeroDocumento' => Escenario::DPI_NUEVO]), 'otro DPI');
    igual(true, $cambia(['tipoDocumento' => null, 'numeroDocumento' => null]), 'quitar el documento');
});

prueba('UNI-06', 'Búsqueda · R-10: prioridad código > documento > nombre; 2/3 letras; formatos de nacimiento; resumen sin datos personales', function () use ($uniCodigo): void {
    igual(['codigo', 'P-000001'], [CriterioBusqueda::desde(' p-000001 ', 'DPI', Escenario::DPI_ANA, null, 'ana')->tipo,
        CriterioBusqueda::desde(' p-000001 ')->codigo], 'el código manda y se normaliza');
    igual('documento', CriterioBusqueda::desde(null, 'DPI', Escenario::DPI_ANA, null, 'ana')->tipo, 'el documento manda sobre el nombre');
    igual('CRITERIO_INSUFICIENTE', $uniCodigo(fn () => CriterioBusqueda::desde(nombre: 'ab')), '2 letras');
    igual('CRITERIO_INSUFICIENTE', $uniCodigo(fn () => CriterioBusqueda::desde(nombre: ' 1 2 3 4 a b ')), 'cuentan las letras, no los caracteres');
    igual('lop', CriterioBusqueda::desde(nombre: 'LÓP')->nombre, '3 letras, normalizadas');
    igual('DATOS_INVALIDOS', $uniCodigo(fn () => CriterioBusqueda::desde(null, 'DPI', '123')), 'documento incompleto');
    foreach (['1985', '1985-03', '1985-03-14'] as $nacimiento) {
        igual($nacimiento, CriterioBusqueda::desde(nombre: 'lopez', nacimiento: $nacimiento)->nacimiento, "nacimiento $nacimiento");
    }
    igual(['DATOS_INVALIDOS', 'DATOS_INVALIDOS'], [$uniCodigo(fn () => CriterioBusqueda::desde(nombre: 'lopez', nacimiento: '85')),
        $uniCodigo(fn () => CriterioBusqueda::desde(nombre: 'lopez', nacimiento: '1985-3'))], 'nacimiento 85 y 1985-3');
    $resumen = CriterioBusqueda::desde(nombre: 'López', nacimiento: '1985')->resumenAuditable();
    igual('nombre(5 letras)+nacimiento', $resumen, 'resumen auditable');
    afirmar(!str_contains($resumen, 'lopez') && !str_contains($resumen, '1985'), 'el resumen no lleva el nombre ni la fecha');
    igual(20, CriterioBusqueda::LIMITE, 'límite de resultados');
});

prueba('UNI-07', 'Búsqueda + confidencialidad · R-11: por nombre se excluye al protegido salvo permiso; por código o documento se encuentra', function () use ($uniGuardada): void {
    $protegida = $uniGuardada(true);
    $porNombre = CriterioBusqueda::desde(nombre: 'lopez');
    igual(false, $porNombre->coincideCon($protegida), 'por nombre, sin leer_protegido');
    igual(true, $porNombre->incluyendoProtegidos(true)->coincideCon($protegida), 'por nombre, con leer_protegido');
    igual(true, $porNombre->coincideCon($uniGuardada(false)), 'por nombre, paciente normal');
    igual(true, CriterioBusqueda::desde('P-000001')->coincideCon($protegida), 'por código (RF-13: se devuelve reducido)');
    igual(true, CriterioBusqueda::desde(null, 'DPI', Escenario::DPI_ANA)->coincideCon($protegida), 'por documento (RF-13)');
    igual(false, CriterioBusqueda::desde(nombre: 'lopez', nacimiento: '1986')->incluyendoProtegidos(true)->coincideCon($protegida), 'otro año');
    igual(true, $porNombre->incluyendoProtegidos(false) !== $porNombre, 'el criterio es inmutable');
});

prueba('UNI-08', 'Confidencialidad · resumen/detalle: el protegido sin permiso se reduce (RF-14, RF-19); el documento SIEMPRE va enmascarado (R-04)', function () use ($uniGuardada): void {
    $reducido = $uniGuardada(true)->detalle(false);
    igual([Paciente::NOMBRE_PROTEGIDO, null, null, null, null, null, null],
        [$reducido['nombre'], $reducido['nacimiento'], $reducido['identificador'], $reducido['telefono'], $reducido['tipo_documento'],
            $reducido['nombres'], $reducido['apellidos']], 'detalle reducido');
    igual(['P-000001', true, 1], [$reducido['codigo'], $reducido['identidad_protegida'], $reducido['version']], 'lo que sí se muestra');
    $completo = $uniGuardada(true)->detalle(true);
    igual(['Ana Lucía López Pérez', 'Ana Lucía', 'López Pérez', '*********0101'],
        [$completo['nombre'], $completo['nombres'], $completo['apellidos'], $completo['identificador']], 'detalle con leer_protegido');
    foreach ([$completo, $uniGuardada(false)->resumen(), $uniGuardada(true)->instantanea()] as $vista) {
        afirmar(!str_contains((string) json_encode($vista), Escenario::DPI_ANA), 'una vista contiene el DPI en claro');
    }
    igual(false, $uniGuardada(false)->seMuestraReducidoPara(false), 'un paciente normal nunca se reduce');
});

prueba('UNI-09', 'Duplicados · R-02/R-03: documento repetido bloquea (RESERVADO si es protegido); homónimo advierte; confirmar exige permiso y motivo; sin documento no es duplicado', function () use ($uniFicha, $uniCodigo): void {
    $almacen = new RepositorioPacientesEnMemoria();
    $politica = new PoliticaDuplicados(new IndiceDuplicadosEnMemoria($almacen));
    $almacen->registrar($uniFicha());                                                                  // P-000001
    $almacen->registrar($uniFicha(['nombres' => 'Sofía', 'numeroDocumento' => Escenario::DPI_LUIS, 'identidadProtegida' => true])); // P-000002
    $detalle = static function (callable $f): array {
        try {
            $f();
            return [];
        } catch (ErrorDominio $e) {
            return [$e->codigo, $e->detalle];
        }
    };
    igual(['PACIENTE_EXISTENTE', ['codigo' => 'P-000001', 'identificador' => '*********0101']],
        $detalle(fn () => $politica->verificarRegistro($uniFicha(['nombres' => 'Otra']), false, false, '', false)), 'mismo DPI');
    igual(['PACIENTE_EXISTENTE', ['codigo' => PoliticaDuplicados::CODIGO_RESERVADO]],
        $detalle(fn () => $politica->verificarRegistro($uniFicha(['numeroDocumento' => Escenario::DPI_LUIS]), false, false, '', false)),
        'DPI de una protegida, sin leer_protegido: ni código ni documento');
    igual('P-000002', $detalle(fn () => $politica->verificarRegistro($uniFicha(['numeroDocumento' => Escenario::DPI_LUIS]), false, false, '', true))[1]['codigo'],
        'con leer_protegido se ve el código');
    $homonima = $uniFicha(['tipoDocumento' => null, 'numeroDocumento' => null]);
    igual(['POSIBLE_DUPLICADO', ['candidatos' => ['P-000001']]], $detalle(fn () => $politica->verificarRegistro($homonima, false, false, '', false)), 'homónima');
    igual('ACCESO_DENEGADO', $uniCodigo(fn () => $politica->verificarRegistro($homonima, true, false, 'Es otra persona', false)), 'confirmar sin permiso');
    igual('MOTIVO_REQUERIDO', $uniCodigo(fn () => $politica->verificarRegistro($homonima, true, true, '   ', false)), 'confirmar sin motivo');
    igual(['P-000001'], $politica->verificarRegistro($homonima, true, true, 'Es otra persona', false), 'confirmada con motivo');
    $almacen->registrar($uniFicha(['nombres' => 'Desconocido', 'apellidos' => 'Uno', 'tipoDocumento' => null, 'numeroDocumento' => null]));
    igual([], $politica->verificarRegistro($uniFicha(['nombres' => 'Desconocido', 'apellidos' => 'Dos', 'tipoDocumento' => null, 'numeroDocumento' => null]),
        false, false, '', false), 'dos provisionales distintos no son duplicados (RF-04)');
    igual(null, $uniCodigo(fn () => $politica->verificarDocumentoNuevo($uniFicha()->conCodigo('P-000001', 1), $uniFicha()->conCodigo('P-000001', 1))),
        'su propio documento no es «de otro»');
});

prueba('UNI-10', 'Edición y confidencialidad · R-08, R-07, R-11: estado restringido, motivo en blanco, visibilidad y cambio de protección', function () use ($uniGuardada, $uniCodigo): void {
    $edicion = new PoliticaEdicion();
    $conf = new PoliticaConfidencialidad();
    $activa = $uniGuardada();
    $telefono = $activa->conDatosDe(Paciente::desdeDatos(Escenario::H_ORIENTE, new DatosPaciente('Ana Lucía', 'López Pérez', '1985-03-14', 'female',
        '+502 4444 0000', 'DPI', Escenario::DPI_ANA), new DateTimeImmutable('2026-10-21')));
    foreach ([Paciente::INACTIVO, Paciente::FALLECIDO] as $estado) {
        igual('REQUIERE_SUPERVISOR', $uniCodigo(fn () => $edicion->verificar($uniGuardada(false, $estado), $telefono, false, 'motivo')), "$estado sin permiso");
        igual(null, $uniCodigo(fn () => $edicion->verificar($uniGuardada(false, $estado), $telefono, true, '')), "$estado con editar_restringido");
    }
    $otroNombre = $activa->conDatosDe(Paciente::desdeDatos(Escenario::H_ORIENTE, new DatosPaciente('Ana María', 'López Pérez', '1985-03-14', 'female'),
        new DateTimeImmutable('2026-10-21')));
    igual('MOTIVO_REQUERIDO', $uniCodigo(fn () => $edicion->verificar($activa, $otroNombre, false, " \t ")), 'motivo solo con espacios');
    igual(null, $uniCodigo(fn () => $edicion->verificar($activa, $telefono, false, '')), 'el teléfono no exige motivo');
    igual(['ACCESO_DENEGADO', null, null], [$uniCodigo(fn () => $conf->exigirVisibilidad($uniGuardada(true), false)),
        $uniCodigo(fn () => $conf->exigirVisibilidad($uniGuardada(true), true)), $uniCodigo(fn () => $conf->exigirVisibilidad($activa, false))],
        'visibilidad: protegida sin / con permiso, normal');
    $desproteger = $uniGuardada(true)->conProteccion(false);
    igual(['ACCESO_DENEGADO', 'MOTIVO_REQUERIDO', null, null], [
        $uniCodigo(fn () => $conf->verificarCambio($uniGuardada(true), $desproteger, false, 'motivo')),
        $uniCodigo(fn () => $conf->verificarCambio($uniGuardada(true), $desproteger, true, '')),
        $uniCodigo(fn () => $conf->verificarCambio($uniGuardada(true), $desproteger, true, 'Cesó la orden judicial')),
        $uniCodigo(fn () => $conf->verificarCambio($uniGuardada(true), $uniGuardada(true), false, '')),
    ], 'cambio de protección: sin permiso / sin motivo / completo / sin cambio');
    igual('ACCESO_DENEGADO', $uniCodigo(fn () => $conf->verificarRegistro($uniGuardada(true), false, 'motivo')), 'registrar protegida sin permiso');
});

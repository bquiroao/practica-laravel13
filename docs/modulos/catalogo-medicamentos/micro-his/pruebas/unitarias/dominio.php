<?php
declare(strict_types=1);

// Reglas de dominio (RN-01, RN-03, RN-04, RN-05) sin base de datos ni dobles.

use Catalogo\Domain\Errores\ErrorReglaNegocio;
use Catalogo\Domain\Errores\ErrorValidacion;
use Catalogo\Domain\Modelo\CodigoMedicamento;
use Catalogo\Domain\Modelo\CriterioBusqueda;
use Catalogo\Domain\Modelo\FichaMedicamento;
use Catalogo\Domain\Modelo\Vigencia;

return [
    'RN-03 la vigencia es [desde, hasta): el día hasta ya no está vigente' => function () {
        $v = new Vigencia(Fabrica::fecha('2026-01-01'), Fabrica::fecha('2026-07-01'));
        Afirmar::verdadero($v->vigenteEn(Fabrica::fecha('2026-01-01')), 'vigente el primer día');
        Afirmar::verdadero($v->vigenteEn(Fabrica::fecha('2026-06-30')), 'vigente el último día');
        Afirmar::verdadero(!$v->vigenteEn(Fabrica::fecha('2026-07-01')), 'no vigente el día hasta');
        Afirmar::verdadero(!$v->vigenteEn(Fabrica::fecha('2025-12-31')), 'no vigente antes de desde');
    },
    'RN-01 vigencias contiguas no se cruzan; abiertas sí' => function () {
        $a = new Vigencia(Fabrica::fecha('2026-01-01'), Fabrica::fecha('2026-07-01'));
        $b = new Vigencia(Fabrica::fecha('2026-07-01'));
        $c = new Vigencia(Fabrica::fecha('2026-06-30'));
        Afirmar::verdadero(!$a->seCruzaCon($b) && !$b->seCruzaCon($a), 'contiguas no se cruzan');
        Afirmar::verdadero($a->seCruzaCon($c) && $b->seCruzaCon($c), 'abierta desde 06-30 se cruza con ambas');
    },
    'RN-05 la ficha válida se acepta y normaliza la concentración' => function () {
        $f = Fabrica::ficha(['concentracion_valor' => '0,900', 'concentracion_unidad' => '%', 'nombre' => '  Cloruro de sodio 0,9 %  ']);
        Afirmar::igual('0.9', $f->concentracion->valor, 'concentración');
        Afirmar::igual('Cloruro de sodio 0,9 %', $f->nombre, 'nombre sin espacios extremos');
    },
    'RN-05 devuelve todos los errores por campo en una sola respuesta (CA-02, CA-03)' => function () {
        [$ficha, $e] = FichaMedicamento::validar(Fabrica::datos([
            'nombre' => str_repeat('a', 151),
            'concentracion_valor' => '0',
            'forma_farmaceutica' => 'GRAGEA',
            'registro_sanitario' => 'rs minúsculas',
        ]));
        Afirmar::igual(null, $ficha, 'ficha');
        Afirmar::igual(['nombre', 'forma_farmaceutica', 'concentracion_valor', 'registro_sanitario'], array_keys($e), 'campos con error');
    },
    'RN-05 límites exactos: 150 caracteres y 999999.999 se aceptan; 1000000 no' => function () {
        [$f] = FichaMedicamento::validar(Fabrica::datos(['nombre' => str_repeat('é', 150), 'concentracion_valor' => '999999.999']));
        Afirmar::verdadero($f !== null, '150 caracteres multibyte y 999999.999 son válidos');
        [, $e] = FichaMedicamento::validar(Fabrica::datos(['concentracion_valor' => '1000000']));
        Afirmar::verdadero(isset($e['concentracion_valor']), '1000000 es inválido');
        [, $e] = FichaMedicamento::validar(Fabrica::datos(['concentracion_valor' => '1.2345']));
        Afirmar::verdadero(isset($e['concentracion_valor']), '4 decimales es inválido');
    },
    'RN-04 la clave ignora mayúsculas y espacios del principio activo' => function () {
        $a = Fabrica::ficha(['principio_activo' => 'Amoxicilina']);
        $b = Fabrica::ficha(['principio_activo' => '  AMOXICILINA ']);
        $c = Fabrica::ficha(['principio_activo' => 'Amoxicilina', 'via_administracion' => 'INTRAVENOSA']);
        Afirmar::verdadero($a->clave()->igualA($b->clave()), 'misma clave');
        Afirmar::verdadero(!$a->clave()->igualA($c->clave()), 'otra vía, otra clave');
    },
    'RN-01 la nueva versión cierra la anterior el día en que empieza (CA-09)' => function () {
        $v1 = Fabrica::medicamento('MED-000101', '2026-01-01');
        [$cerrada, $v2] = $v1->nuevaVersion(Fabrica::uuid(), Fabrica::ficha(['presentacion' => 'Caja con 30 cápsulas']), Fabrica::fecha('2026-11-01'), 'qf.norte');
        Afirmar::igual('2026-11-01', $cerrada->vigencia->hasta?->format('Y-m-d'), 'hasta de la versión 1');
        Afirmar::igual(2, $v2->version, 'versión nueva');
        Afirmar::igual('MED-000101', $v2->codigo->valor, 'mismo código');
        Afirmar::verdadero(!$cerrada->vigencia->seCruzaCon($v2->vigencia), 'nunca dos vigentes a la vez');
    },
    'RN-01 una versión que no empieza después de la vigente es SOLAPE_VIGENCIA (CA-10)' => function () {
        $v1 = Fabrica::medicamento('MED-000101', '2026-01-01');
        foreach (['2026-01-01', '2025-12-31'] as $desde) {
            Afirmar::lanza(ErrorReglaNegocio::class, fn () => $v1->nuevaVersion(Fabrica::uuid(), Fabrica::ficha(), Fabrica::fecha($desde), 'qf.norte'), 'SOLAPE_VIGENCIA');
        }
    },
    'RN-02 la baja cierra la vigencia y una versión cerrada no admite cambios' => function () {
        $baja = Fabrica::medicamento('MED-000105', '2026-01-01')->darDeBaja(Fabrica::fecha('2026-09-01'), 'Sustituido (dato ficticio)');
        Afirmar::igual('2026-09-01', $baja->vigencia->hasta?->format('Y-m-d'), 'fecha de baja');
        Afirmar::lanza(ErrorReglaNegocio::class, fn () => $baja->darDeBaja(Fabrica::fecha('2026-10-01'), 'Otra vez'), 'YA_DADO_DE_BAJA');
        Afirmar::lanza(ErrorReglaNegocio::class, fn () => $baja->nuevaVersion(Fabrica::uuid(), Fabrica::ficha(), Fabrica::fecha('2026-10-01'), 'qf.norte'), 'YA_DADO_DE_BAJA');
    },
    'RN-02 baja con fecha no posterior o motivo corto es ErrorValidacion' => function () {
        $m = Fabrica::medicamento('MED-000105', '2026-01-01');
        $e = Afirmar::lanza(ErrorValidacion::class, fn () => $m->darDeBaja(Fabrica::fecha('2026-01-01'), 'no'));
        Afirmar::igual(['fecha_baja', 'motivo'], array_keys($e->detalles), 'campos');
    },
    'RN-03 el criterio toma % y _ como texto literal' => function () {
        $cloruro = Fabrica::medicamento('MED-000106', '2026-01-01', ['nombre' => 'Cloruro de sodio 0,9 % solución inyectable', 'principio_activo' => 'Cloruro de sodio']);
        $hoy = Fabrica::fecha(Fabrica::HOY);
        Afirmar::verdadero(CriterioBusqueda::desdeFiltros(['q' => '0,9 %'], $hoy)->coincide($cloruro), '"0,9 %" coincide');
        Afirmar::verdadero(!CriterioBusqueda::desdeFiltros(['q' => '0_9'], $hoy)->coincide($cloruro), '"_" no es comodín');
    },
    'RN-03 el criterio exige texto o forma y valida la fecha' => function () {
        $e = Afirmar::lanza(ErrorValidacion::class, fn () => CriterioBusqueda::desdeFiltros(['q' => ' ', 'fecha' => '2026-02-30'], Fabrica::fecha(Fabrica::HOY)));
        Afirmar::igual(['q', 'fecha'], array_keys($e->detalles), 'campos');
    },
    'RN-06 el código tiene el formato MED-000000' => function () {
        Afirmar::igual('MED-000107', CodigoMedicamento::desdeTexto(' med-000107 ')->valor, 'normaliza');
        Afirmar::lanza(ErrorValidacion::class, fn () => CodigoMedicamento::desdeTexto('MED-107'));
    },
];

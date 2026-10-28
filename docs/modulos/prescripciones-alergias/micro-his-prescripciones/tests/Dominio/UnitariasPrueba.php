<?php

declare(strict_types=1);

/*
 * Semana 15 · Pruebas UNITARIAS: cada una prueba UNA clase del Dominio, sin kernel, sin transacción y sin base.
 * Un tema de la tarea por prueba: prescripción, medicamentos, dosis, alergias, validación previa y bloqueo seguro.
 */

use Prescripciones\Dominio\Modelo\Alergia;
use Prescripciones\Dominio\Modelo\Anulacion;
use Prescripciones\Dominio\Modelo\DecisionEmision;
use Prescripciones\Dominio\Modelo\ExpedienteAlergias;
use Prescripciones\Dominio\Modelo\Medicamento;
use Prescripciones\Dominio\Modelo\Prescripcion;
use Prescripciones\Dominio\Modelo\ResultadoVerificacion;
use Prescripciones\Dominio\Modelo\SolicitudEmision;
use Prescripciones\Dominio\Reglas\PoliticaAnulacion;
use Prescripciones\Dominio\Reglas\PoliticaEmision;
use Prescripciones\Dominio\Reglas\ValidadorPrescripcion;
use Prescripciones\Dominio\Reglas\VerificadorAlergias;

$uniMed = [
    'AMOX500' => new Medicamento('AMOX500', 'Amoxicilina 500 mg', 'J01CA04', 'Amoxicilina', 'betalactamicos', 'mg', 3000.0, ['oral'], true),
    'CEFA500' => new Medicamento('CEFA500', 'Cefalexina 500 mg', 'J01DB01', 'Cefalexina', 'betalactamicos', 'mg', 4000.0, ['oral'], true),
    'PARA500' => new Medicamento('PARA500', 'Paracetamol 500 mg', 'N02BE01', 'Paracetamol', 'anilidas', 'mg', 4000.0, ['oral', 'rectal'], true),
    'RANI150' => new Medicamento('RANI150', 'Ranitidina 150 mg', 'A02BA02', 'Ranitidina', 'antih2', 'mg', 300.0, ['oral'], false),
];
$uniItem = static fn (string $m, int|float $dosis = 500, int $frec = 8, string $via = 'oral', int $dias = 5, string $unidad = 'mg'): array
    => ['medicamento' => $m, 'dosis' => $dosis, 'unidad' => $unidad, 'frecuencia_horas' => $frec, 'via' => $via, 'dias' => $dias];
/** El catálogo ya resuelto, como lo arma EvaluacionClinica: código => medicamento (o null si no existe). */
$uniValidar = static function (array $items) use ($uniMed): array {
    $catalogo = [];
    foreach ($items as $i) {
        $catalogo[$i['medicamento']] = $uniMed[$i['medicamento']] ?? null;
    }

    return (new ValidadorPrescripcion())->validar($items, $catalogo);
};
$uniActiva = static fn (?string $codigo, ?string $nombre, string $severidad = 'severe', string $estado = 'active'): Alergia
    => new Alergia($codigo, $nombre, 'allergy', $severidad, $estado, null);
$uniVerificar = static fn (array $meds, array $alergias, array $grupos = []): ResultadoVerificacion
    => (new VerificadorAlergias())->verificar(array_map(static fn (string $c) => $uniMed[$c], $meds), new ExpedienteAlergias($alergias !== [], $alergias), $grupos);

prueba('UNI-01', 'Prescripción · inmutable: todas las propiedades son readonly, anular devuelve OTRA instancia y la dosis queda decimal', function () use ($uniItem): void {
    $p = new Prescripcion(1, 'hosp-norte', 'P-005', 'dr-lopez', 'k', str_repeat('a', 64), [$uniItem('PARA500', 500)],
        new ResultadoVerificacion(ResultadoVerificacion::SIN_HALLAZGOS, []), [], null, false, new DateTimeImmutable('2026-10-27T10:00:00Z'));
    $noReadonly = array_values(array_map(static fn (ReflectionProperty $r): string => $r->getName(),
        array_filter((new ReflectionClass(Prescripcion::class))->getProperties(), static fn (ReflectionProperty $r): bool => !$r->isReadOnly())));
    igual([], $noReadonly, 'propiedades que se podrían cambiar');
    $anulada = $p->conAnulacion(new Anulacion(1, 'hosp-norte', 'Pauta duplicada con la de urgencias', 'dr-lopez', new DateTimeImmutable()));
    igual(['EMITIDA', 'ANULADA', false], [$p->estado, $anulada->estado, $p === $anulada], 'la original no cambia');
    igual(500.0, $p->items[0]['dosis'], 'dosis normalizada a decimal (H-4)');
});

prueba('UNI-02', 'Dosis · la dosis diaria máxima es inclusiva: 1000 mg c/6 h = 4000 sí; 1000,5 no; 4000 c/24 h sí; 0,001 sí', function () use ($uniItem, $uniValidar): void {
    igual(['campos' => [], 'dosis' => []], $uniValidar([$uniItem('PARA500', 1000, 6)]), '4000 mg/día');
    igual(['campos' => [], 'dosis' => ['items[0]']], $uniValidar([$uniItem('PARA500', 1000.5, 6)]), '4002 mg/día');
    igual(['campos' => [], 'dosis' => []], $uniValidar([$uniItem('PARA500', 4000, 24)]), 'una toma de 4000');
    igual(['campos' => [], 'dosis' => []], $uniValidar([$uniItem('PARA500', 0.001, 24)]), 'dosis mínima positiva');
});

prueba('UNI-03', 'Medicamentos · completitud: días 1 y 90 sí, 0 y 91 no; frecuencia, vía y unidad del catálogo; un inactivo se rechaza solo por el medicamento', function () use ($uniItem, $uniValidar): void {
    igual([], $uniValidar([$uniItem('PARA500', dias: 1), $uniItem('PARA500', dias: 90)])['campos'], 'días 1 y 90');
    igual(['items[0].dias', 'items[1].dias'], $uniValidar([$uniItem('PARA500', dias: 0), $uniItem('PARA500', dias: 91)])['campos'], 'días 0 y 91');
    igual(['items[0].frecuencia_horas', 'items[1].via', 'items[2].unidad'],
        $uniValidar([$uniItem('PARA500', frec: 5), $uniItem('AMOX500', via: 'rectal'), $uniItem('PARA500', unidad: 'g')])['campos'], 'frecuencia, vía y unidad');
    igual(['items[0].medicamento'], $uniValidar([$uniItem('RANI150', 999, 5, 'iv', 0)])['campos'], 'inactivo: no se evalúa nada más del ítem');
    igual(['items'], $uniValidar([])['campos'], 'sin ítems');
});

prueba('UNI-04', 'Alergias · coincidencia directa: por código sin importar mayúsculas ni espacios; por nombre normalizado si no hay código; una resuelta no cuenta', function () use ($uniActiva, $uniVerificar): void {
    igual('BLOQUEO', $uniVerificar(['AMOX500'], [$uniActiva(' j01ca04 ', null)])->estado, 'código en minúsculas con espacios');
    igual('BLOQUEO', $uniVerificar(['AMOX500'], [$uniActiva(null, '  ÁMOXICILINA ')])->estado, 'nombre con tilde, mayúsculas y espacios');
    igual('SIN_HALLAZGOS', $uniVerificar(['AMOX500'], [$uniActiva(null, 'Amoxicilina', estado: 'resolved')])->estado, 'resuelta');
    igual('SIN_HALLAZGOS', $uniVerificar(['AMOX500'], [$uniActiva('X99', 'Amoxicilina')])->estado, 'con código, el nombre no se mira (comportamiento documentado)');
    $h = $uniVerificar(['AMOX500'], [$uniActiva('J01CA04', 'Amoxicilina')])->hallazgos[0];
    igual(['DIRECTA', true, 'J01CA04'], [$h->tipo, $h->bloquea, $h->alergeno], 'hallazgo');
});

prueba('UNI-05', 'Alergias · reactividad cruzada: moderada y grave advierten; potencialmente mortal y desconocida bloquean; sin grupo no hay cruzada; la directa manda en el código', function () use ($uniActiva, $uniVerificar): void {
    $grupo = ['J01CA04' => 'betalactamicos'];
    $estados = array_map(static fn (string $sev): string => $uniVerificar(['CEFA500'], [$uniActiva('J01CA04', null, $sev)], $grupo)->estado,
        ['mild', 'moderate', 'severe', 'life_threatening', 'unknown']);
    igual(['ADVERTENCIA', 'ADVERTENCIA', 'ADVERTENCIA', 'BLOQUEO', 'BLOQUEO'], $estados, 'por severidad');
    igual('SIN_HALLAZGOS', $uniVerificar(['CEFA500'], [$uniActiva('J01CA04', null)])->estado, 'sin grupo conocido');
    $ambas = $uniVerificar(['AMOX500', 'CEFA500'], [$uniActiva('J01CA04', null, 'life_threatening')], $grupo);
    igual(['BLOQUEO', 'BLOQUEO_ALERGIA'], [$ambas->estado, $ambas->codigoBloqueo()], 'directa + cruzada');
    igual('BLOQUEO_ALERGIA_CRUZADA', $uniVerificar(['CEFA500'], [$uniActiva('J01CA04', null, 'unknown')], $grupo)->codigoBloqueo(), 'solo cruzada');
});

prueba('UNI-06', 'Alergias · una alergia sin código ni nombre, o con una severidad fuera del esquema, no existe; impideJustificar solo en mortal y desconocida', function (): void {
    lanza(InvalidArgumentException::class, static fn () => new Alergia('  ', null, 'allergy', 'severe', 'active'), 'sin alérgeno');
    lanza(InvalidArgumentException::class, static fn () => new Alergia('J01CA04', null, 'allergy', 'grave', 'active'), 'severidad en español');
    $impide = array_map(static fn (string $s): bool => (new Alergia('J01CA04', null, 'allergy', $s, 'active'))->impideJustificar(),
        ['mild', 'moderate', 'severe', 'life_threatening', 'unknown']);
    igual([false, false, false, true, true], $impide, 'impideJustificar');
});

prueba('UNI-07', 'Validación previa · PoliticaEmision: orden confirmación → bloqueo → advertencia; justificación 19/20 sin contar espacios; override solo con advertencia', function (): void {
    $pol = new PoliticaEmision();
    $consultas = 0;
    $override = static function (bool $tiene) use (&$consultas): Closure {
        return static function () use ($tiene, &$consultas): bool {
            $consultas++;

            return $tiene;
        };
    };
    $sol = static fn (?string $j = null, bool $conf = false): SolicitudEmision => new SolicitudEmision('dr-lopez', 'hosp-norte', 'P-002', 'k', [], $j, $conf);
    $v = static fn (string $estado): ResultadoVerificacion => new ResultadoVerificacion($estado, []);
    $evaluado = new ExpedienteAlergias(true, []);
    $sinRegistros = new ExpedienteAlergias(false, []);
    $t = static fn (DecisionEmision $d): string => $d->tipo;

    igual('REQUIERE_CONFIRMACION', $t($pol->decidir($v('BLOQUEO'), $sinRegistros, $sol(str_repeat('x', 30)), $override(true))), 'la confirmación va primero');
    $conf = $pol->decidir($v('SIN_HALLAZGOS'), $sinRegistros, $sol(null, true), $override(true));
    igual(['PERMITIDA', true], [$conf->tipo, $conf->confirmacionRegistrada], 'confirmó sin alergias');
    igual('BLOQUEADA', $t($pol->decidir($v('BLOQUEO'), $evaluado, $sol(str_repeat('x', 30)), $override(true))), 'el bloqueo no se justifica');
    igual(0, $consultas, 'sin advertencia no se pregunta a RBAC');
    igual('REQUIERE_JUSTIFICACION', $t($pol->decidir($v('ADVERTENCIA'), $evaluado, $sol('  ' . str_repeat('x', 19) . '  '), $override(true))), '19 sin contar espacios');
    $ok = $pol->decidir($v('ADVERTENCIA'), $evaluado, $sol('  ' . str_repeat('x', 20) . '  '), $override(true));
    igual(['PERMITIDA', str_repeat('x', 20)], [$ok->tipo, $ok->justificacion], '20: se guarda recortada');
    igual('REQUIERE_JUSTIFICACION', $t($pol->decidir($v('ADVERTENCIA'), $evaluado, $sol(str_repeat('x', 20)), $override(false))), 'sin override');
    igual([null, null, null, true, false], [
        $pol->puedeJustificar($v('SIN_HALLAZGOS'), $evaluado, $override(true)), $pol->puedeJustificar($v('BLOQUEO'), $evaluado, $override(true)),
        $pol->puedeJustificar($v('ADVERTENCIA'), $sinRegistros, $override(true)), $pol->puedeJustificar($v('ADVERTENCIA'), $evaluado, $override(true)),
        $pol->puedeJustificar($v('ADVERTENCIA'), $evaluado, $override(false)),
    ], 'puedeJustificar (semana 12)');
});

prueba('UNI-08', 'Bloqueo seguro de la anulación · PoliticaAnulacion: el estado se mira antes que el motivo; motivo de 9 no, de 10 sí, sin contar espacios', function () use ($uniItem): void {
    $p = new Prescripcion(1, 'hosp-norte', 'P-005', 'dr-lopez', 'k', str_repeat('a', 64), [$uniItem('PARA500')],
        new ResultadoVerificacion(ResultadoVerificacion::SIN_HALLAZGOS, []), [], null, false, new DateTimeImmutable());
    $anulada = $p->conAnulacion(new Anulacion(1, 'hosp-norte', 'Pauta duplicada con la de urgencias', 'dr-lopez', new DateTimeImmutable()));
    $pol = new PoliticaAnulacion();
    igual('ESTADO_NO_PERMITE_ANULAR', $pol->rechazo($anulada, 'corto'), 'ya anulada, aun con motivo corto');
    igual('MOTIVO_REQUERIDO', $pol->rechazo($p, '   ' . str_repeat('m', 9) . '   '), '9 caracteres');
    igual(null, $pol->rechazo($p, str_repeat('m', 10)), '10 caracteres');
});

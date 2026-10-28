<?php

declare(strict_types=1);

/*
 * Suite de CONTRATO: las mismas 7 verificaciones, escritas una sola vez, contra los dos adaptadores
 * (InMemory y PDO). Si un adaptador se aparta del contrato, falla SU fila (CON-MEM-xx o CON-PDO-xx).
 */

use Prescripciones\Dominio\Excepciones\ClaveEnUso;
use Prescripciones\Dominio\Modelo\Alergia;
use Prescripciones\Dominio\Modelo\Hallazgo;
use Prescripciones\Dominio\Modelo\Medicamento;
use Prescripciones\Dominio\Modelo\Prescripcion;
use Prescripciones\Dominio\Modelo\ResultadoVerificacion;
use Prescripciones\Persistencia\FabricaAdaptadores;

/** Una prescripción válida para guardar (P-002 con advertencia justificada). */
$prescripcion = static function (string $clave, string $hospital = 'hosp-norte', string $paciente = 'P-002'): Prescripcion {
    $s = Escenario::solicitud($paciente, [Escenario::item('CEFA500', 500, 6, 'oral', 7), Escenario::item('PARA500', 750.5, 8, 'rectal', 3)],
        justificacion: 'Sin alternativa: cultivo sensible solo a cefalosporinas', clave: $clave);

    return new Prescripcion(null, $hospital, $paciente, 'dr-lopez', $clave, $s->huella(), $s->items,
        new ResultadoVerificacion(ResultadoVerificacion::ADVERTENCIA, [new Hallazgo(Hallazgo::CRUZADA, 'CEFA500', 'Penicilina', 'moderate', false, 'exantema')]),
        ['Penicilina'], $s->justificacion, false, new \DateTimeImmutable('2026-10-27T15:00:00+00:00'));
};

/** Lo que un lector debe ver igual, venga de donde venga. */
$vista = static fn (Prescripcion $p): array => ['hospital' => $p->hospital, 'clave' => $p->clave, 'huella' => $p->huella] + $p->aArreglo();

$fabricas = [
    'MEM' => static fn (): array => FabricaAdaptadores::memoria(Escenario::datos(), Escenario::config()),
    'PDO' => static function (): array {
        [$pdo, $config] = baseLimpia();

        return FabricaAdaptadores::pdo($pdo, $config);
    },
];

foreach ($fabricas as $nombre => $fabrica) {
    prueba("CON-$nombre-01", "[$nombre] guardar asigna ids crecientes y lo leído es idéntico a lo guardado (ítems, verificación, fecha)",
        function () use ($fabrica, $prescripcion, $vista): void {
            $a = $fabrica();
            $p1 = $a['repositorio']->guardar($prescripcion('c-1'));
            $p2 = $a['repositorio']->guardar($prescripcion('c-2'));
            afirmar($p2->id > $p1->id, 'ids crecientes');
            igual($vista($p1), $vista($a['repositorio']->porId('hosp-norte', $p1->id)), 'lo leído');
        });

    prueba("CON-$nombre-02", "[$nombre] porClave busca por (hospital, clave); otro hospital no la ve",
        function () use ($fabrica, $prescripcion): void {
            $a = $fabrica();
            $p = $a['repositorio']->guardar($prescripcion('c-1'));
            igual($p->id, $a['repositorio']->porClave('hosp-norte', 'c-1')?->id, 'mismo hospital');
            igual([null, null], [$a['repositorio']->porClave('hosp-sur', 'c-1'), $a['repositorio']->porClave('hosp-norte', 'otra')], 'otro hospital / otra clave');
        });

    prueba("CON-$nombre-03", "[$nombre] la misma clave en el mismo hospital → ClaveEnUso; en otro hospital se acepta",
        function () use ($fabrica, $prescripcion): void {
            $a = $fabrica();
            $a['repositorio']->guardar($prescripcion('c-1'));
            lanza(ClaveEnUso::class, static fn () => $a['repositorio']->guardar($prescripcion('c-1')), 'mismo hospital');
            $a['repositorio']->guardar($prescripcion('c-1', 'hosp-sur', 'P-900'));
        });

    prueba("CON-$nombre-04", "[$nombre] porId fuera del hospital o inexistente → null (R-08)",
        function () use ($fabrica, $prescripcion): void {
            $a = $fabrica();
            $p = $a['repositorio']->guardar($prescripcion('c-1'));
            igual([null, null], [$a['repositorio']->porId('hosp-sur', $p->id), $a['repositorio']->porId('hosp-norte', 999999)], 'resultados');
        });

    prueba("CON-$nombre-05", "[$nombre] la transacción revierte lo guardado si el trabajo falla, y confirma si no",
        function () use ($fabrica, $prescripcion): void {
            $a = $fabrica();
            lanza(\RuntimeException::class, static fn () => $a['transaccion']->ejecutar(static function () use ($a, $prescripcion): void {
                $a['repositorio']->guardar($prescripcion('c-1'));
                $a['auditoria']->registrar('PRESCRIPCION_EMITIDA', 'hosp-norte', 'dr-lopez', 'prescripcion:1', 'exito');
                throw new \RuntimeException('falla a mitad');
            }), 'trabajo que falla');
            igual(null, $a['repositorio']->porClave('hosp-norte', 'c-1'), 'revertida');
            $a['transaccion']->ejecutar(static fn () => $a['repositorio']->guardar($prescripcion('c-1')));
            afirmar($a['repositorio']->porClave('hosp-norte', 'c-1') !== null, 'confirmada');
        });

    prueba("CON-$nombre-06", "[$nombre] FuenteAlergias devuelve el expediente de database/datos-demo (activas, resueltas, sin código, no evaluado)",
        function () use ($fabrica): void {
            $a = $fabrica();
            $esperadas = Escenario::alergias();
            foreach (['P-001', 'P-002', 'P-003', 'P-004', 'P-005', 'P-006', 'P-007'] as $p) {
                $exp = $a['alergias']->alergiasDe($p, 'hosp-norte');
                igual([isset($esperadas[$p]), serialize($esperadas[$p] ?? [])], [$exp->evaluado, serialize($exp->alergias)], "expediente de $p");
            }
        });

    prueba("CON-$nombre-07", "[$nombre] CatalogoMedicamentos: medicamentos y grupo del alérgeno por código o por nombre",
        function () use ($fabrica): void {
            $a = $fabrica();
            foreach (Escenario::datos()['medicamentos'] as $m) {
                igual(serialize(new Medicamento(...$m)), serialize($a['catalogo']->buscar($m[0])), "medicamento {$m[0]}");
            }
            igual(null, $a['catalogo']->buscar('NOEXISTE'), 'inexistente');
            $grupos = array_map(static fn (array $l): ?string => $a['catalogo']->grupoDelAlergeno($l[0]), Escenario::alergias());
            igual(['P-001' => 'betalactamicos', 'P-002' => 'betalactamicos', 'P-004' => 'aines', 'P-005' => 'betalactamicos',
                'P-006' => 'betalactamicos', 'P-007' => null], $grupos, 'grupos');
            igual(null, $a['catalogo']->grupoDelAlergeno(new Alergia('Z99', 'desconocido', 'allergy', 'mild', 'active')), 'sin grupo');
        });
}

foreach ($fabricas as $nombre => $fabrica) {
    prueba("CON-$nombre-08", "[$nombre] semana 6: anular agrega la anulación sin cambiar la prescripción; una segunda → YaAnulada; se revierte con la transacción",
        function () use ($fabrica, $prescripcion, $vista): void {
            $a = $fabrica();
            $p = $a['repositorio']->guardar($prescripcion('c-1'));
            $anulacion = new \Prescripciones\Dominio\Modelo\Anulacion($p->id, 'hosp-norte', 'Alergia nueva registrada', 'dr-lopez',
                new \DateTimeImmutable('2026-10-28T10:00:00+00:00'));
            lanza(\RuntimeException::class, static fn () => $a['transaccion']->ejecutar(static function () use ($a, $anulacion): void {
                $a['repositorio']->anular($anulacion);
                throw new \RuntimeException('falla a mitad');
            }), 'anulación revertida');
            igual('EMITIDA', $a['repositorio']->porId('hosp-norte', $p->id)->estado, 'sigue emitida tras el rollback');
            $a['repositorio']->anular($anulacion);
            $leida = $a['repositorio']->porId('hosp-norte', $p->id);
            igual(['ANULADA', $anulacion->aArreglo()], [$leida->estado, $leida->anulacion?->aArreglo()], 'leída con su anulación');
            igual(array_diff_key($vista($p), ['estado' => 1]), array_diff_key($vista($leida), ['estado' => 1, 'anulacion' => 1]), 'lo emitido no cambió');
            igual('ANULADA', $a['repositorio']->porClave('hosp-norte', 'c-1')->estado, 'también por clave');
            lanza(\Prescripciones\Dominio\Excepciones\YaAnulada::class, static fn () => $a['repositorio']->anular($anulacion), 'segunda anulación');
        });
}

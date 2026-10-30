<?php

declare(strict_types=1);

namespace E2e\Dominio;

/**
 * R-09: la regla de calidad, sin ningún detalle. Recibe datos ya traducidos y una función que confirma evidencia;
 * no sabe si vienen de GitHub, de GitLab o de un archivo, ni dónde están guardados los artefactos.
 */
final class QualityGate
{
    /** @param callable(string): bool $evidenciaConfirmada */
    public static function evaluar(PoliticaGate $politica, Ejecucion $ejecucion, string $digestVersion, \DateTimeImmutable $ahora,
        callable $evidenciaConfirmada): Decision
    {
        if (!$ejecucion->completada) {
            throw new ReglaRechazada('EJECUCION_NO_COMPLETADA');
        }
        $criticosQueNoPasan = [];
        $sinEvidencia = [];
        $noCriticos = 0;
        $pasanNoCriticos = 0;
        $inestables = 0;
        foreach ($ejecucion->resultados as $r) {
            if ($r->critico && $r->resultado !== Resultado::PASA) {
                $criticosQueNoPasan[] = $r->escenario;
            }
            if ($r->resultado->exigeEvidencia() && ($r->evidencias === [] || count(array_filter($r->evidencias, $evidenciaConfirmada)) < count($r->evidencias))) {
                $sinEvidencia[] = $r->escenario;
            }
            if ($r->resultado === Resultado::INESTABLE) {
                $inestables++;
            }
            // Un OMITIDO cuenta como no aprobado: si saliera del denominador, omitir el escenario que falla subiría la tasa.
            if (!$r->critico) {
                $noCriticos++;
                $pasanNoCriticos += $r->resultado === Resultado::PASA ? 1 : 0;
            }
        }
        $tasa = $noCriticos === 0 ? 1.0 : $pasanNoCriticos / $noCriticos;
        $horas = ($ahora->getTimestamp() - $ejecucion->terminadaEn->getTimestamp()) / 3600;

        $reglas = [
            'digest' => $ejecucion->digest === $digestVersion,                       // R-03
            'a_criticos' => $criticosQueNoPasan === [],                              // R-09a
            'b_tasa' => $tasa >= $politica->umbralNoCriticos,                        // R-09b
            'c_inestables' => $inestables <= $politica->maxInestables,               // R-09c
            'd_evidencia' => $sinEvidencia === [],                                   // R-09d y R-05
            'e_antiguedad' => $horas <= $politica->antiguedadMaximaHoras,            // R-09e
        ];
        $causas = array_values(array_filter([
            $reglas['digest'] ? null : 'la ejecución probó otro digest',
            $reglas['a_criticos'] ? null : 'críticos que no pasan: ' . implode(', ', $criticosQueNoPasan),
            $reglas['b_tasa'] ? null : sprintf('tasa de no críticos %.1f %% menor que %.1f %%', 100 * $tasa, 100 * $politica->umbralNoCriticos),
            $reglas['c_inestables'] ? null : "$inestables inestables (máximo {$politica->maxInestables})",
            $reglas['d_evidencia'] ? null : 'sin evidencia confirmada: ' . implode(', ', $sinEvidencia),
            $reglas['e_antiguedad'] ? null : sprintf('ejecución de %.0f h (máximo %d h)', $horas, $politica->antiguedadMaximaHoras),
        ]));

        return new Decision($causas === [] ? Decision::APROBADO : Decision::RECHAZADO, $reglas, $causas, $ejecucion->id, $digestVersion,
            $politica->version);
    }
}

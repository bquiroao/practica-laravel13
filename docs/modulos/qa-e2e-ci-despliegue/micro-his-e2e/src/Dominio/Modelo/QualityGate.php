<?php

declare(strict_types=1);

namespace E2e\Dominio\Modelo;

use E2e\Dominio\Excepciones\ReglaRechazada;

/**
 * R-03 y R-09 (semana 2): la regla de calidad, pura. Recibe la ejecución, la versión, la hora y una función que confirma
 * evidencia; no sabe de dónde vienen ni dónde se guardan.
 */
final class QualityGate
{
    /** @param callable(string): bool $evidenciaConfirmada */
    public static function evaluar(PoliticaGate $politica, Ejecucion $ejecucion, Version $version, \DateTimeImmutable $ahora,
        callable $evidenciaConfirmada, string $usuario): Decision
    {
        if ($ejecucion->estado() !== Ejecucion::COMPLETADA) {
            throw new ReglaRechazada('EJECUCION_NO_COMPLETADA');
        }
        if ($ejecucion->version !== $version->numero) {
            throw new ReglaRechazada('EJECUCION_DE_OTRA_VERSION');
        }
        $criticosQueNoPasan = [];
        $sinEvidencia = [];
        $noCriticos = 0;
        $pasanNoCriticos = 0;
        $inestables = 0;
        foreach ($ejecucion->resultados() as $r) {
            if ($r->critico && $r->resultado !== Resultado::PASA) {
                $criticosQueNoPasan[] = $r->escenario;
            }
            $huellas = array_map(static fn (Evidencia $e): string => $e->sha256, $r->evidencias);
            if ($r->resultado->exigeEvidencia() && ($huellas === [] || count(array_filter($huellas, $evidenciaConfirmada)) < count($huellas))) {
                $sinEvidencia[] = $r->escenario;
            }
            $inestables += $r->resultado === Resultado::INESTABLE ? 1 : 0;
            // Un OMITIDO cuenta como no aprobado: si saliera del denominador, omitir el escenario que falla subiría la tasa.
            if (!$r->critico) {
                $noCriticos++;
                $pasanNoCriticos += $r->resultado === Resultado::PASA ? 1 : 0;
            }
        }
        $tasa = $noCriticos === 0 ? 1.0 : $pasanNoCriticos / $noCriticos;
        $horas = ($ahora->getTimestamp() - (int) $ejecucion->terminadaEn()?->getTimestamp()) / 3600;

        $reglas = [
            'digest' => $ejecucion->digest === $version->digest,
            'a_criticos' => $criticosQueNoPasan === [],
            'b_tasa' => $tasa >= $politica->umbralNoCriticos,
            'c_inestables' => $inestables <= $politica->maxInestables,
            'd_evidencia' => $sinEvidencia === [],
            'e_antiguedad' => $horas <= $politica->antiguedadMaximaHoras,
        ];
        $causas = array_values(array_filter([
            $reglas['digest'] ? null : 'la ejecución probó otro digest',
            $reglas['a_criticos'] ? null : 'críticos que no pasan: ' . implode(', ', $criticosQueNoPasan),
            $reglas['b_tasa'] ? null : sprintf('tasa de no críticos %.1f %% menor que %.1f %%', 100 * $tasa, 100 * $politica->umbralNoCriticos),
            $reglas['c_inestables'] ? null : "$inestables inestables (máximo {$politica->maxInestables})",
            $reglas['d_evidencia'] ? null : 'sin evidencia confirmada: ' . implode(', ', $sinEvidencia),
            $reglas['e_antiguedad'] ? null : sprintf('ejecución de %.0f h (máximo %d h)', $horas, $politica->antiguedadMaximaHoras),
        ]));

        return new Decision($causas === [] ? Decision::APROBADO : Decision::RECHAZADO, $reglas, $causas, $ejecucion->tenant, $version->numero,
            (int) $ejecucion->id, $version->digest, $politica->version, $ahora, $usuario);
    }
}

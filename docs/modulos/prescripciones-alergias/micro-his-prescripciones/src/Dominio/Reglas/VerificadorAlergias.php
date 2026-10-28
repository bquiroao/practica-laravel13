<?php

declare(strict_types=1);

namespace Prescripciones\Dominio\Reglas;

use Prescripciones\Dominio\Modelo\Medicamento;
use Prescripciones\Dominio\Modelo\Alergia;
use Prescripciones\Dominio\Modelo\ExpedienteAlergias;
use Prescripciones\Dominio\Modelo\Hallazgo;
use Prescripciones\Dominio\Modelo\ResultadoVerificacion;

/**
 * Regla de dominio pura (R-04, R-05): sin E/S, sin base, sin reloj.
 * Recibe los medicamentos, el expediente y el grupo farmacológico de cada alérgeno ya resuelto.
 */
final class VerificadorAlergias
{
    /**
     * @param list<Medicamento>     $medicamentos
     * @param array<string, string> $gruposDeAlergenos alérgeno => grupo farmacológico
     */
    public function verificar(array $medicamentos, ExpedienteAlergias $expediente, array $gruposDeAlergenos): ResultadoVerificacion
    {
        $hallazgos = [];
        foreach ($medicamentos as $m) {
            foreach ($expediente->activas() as $a) {
                if ($this->coincideDirecto($m, $a)) {
                    $hallazgos[] = new Hallazgo(Hallazgo::DIRECTA, $m->codigo, $a->alergeno(), $a->severidad, true, $a->reaccion);
                } elseif (($gruposDeAlergenos[$a->alergeno()] ?? null) === $m->grupo) {
                    $hallazgos[] = new Hallazgo(Hallazgo::CRUZADA, $m->codigo, $a->alergeno(), $a->severidad, $a->impideJustificar(), $a->reaccion);
                }
            }
        }

        return ResultadoVerificacion::desde($hallazgos);
    }

    /** Por código si la alergia lo tiene; si solo tiene nombre, por nombre normalizado. */
    private function coincideDirecto(Medicamento $m, Alergia $a): bool
    {
        if ($a->codigo !== null && trim($a->codigo) !== '') {
            return strcasecmp(trim($a->codigo), $m->principioActivoCodigo) === 0;
        }

        return self::normalizar((string) $a->nombre) === self::normalizar($m->principioActivo);
    }

    public static function normalizar(string $texto): string
    {
        $t = mb_strtolower(trim($texto));

        return strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }
}

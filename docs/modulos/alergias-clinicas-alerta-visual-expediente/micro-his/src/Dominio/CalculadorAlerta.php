<?php
declare(strict_types=1);

namespace Alergias\Dominio;

use Alergias\Dominio\Reglas\ReglaNivelAlerta;

/** CU-04: nivel = el mayor que asigne cualquier regla a cualquier alergia activa (RN-08). */
final class CalculadorAlerta
{
    /** @var list<ReglaNivelAlerta> */
    private array $reglas;

    public function __construct(ReglaNivelAlerta ...$reglas)
    {
        $this->reglas = $reglas;
    }

    /** @param list<Alergia> $activas */
    public function calcular(string $codigoPaciente, array $activas): AlertaExpediente
    {
        $detalle = [];
        $general = NivelAlerta::SinRegistro;
        foreach ($activas as $alergia) {
            $nivel = NivelAlerta::Informativa;
            foreach ($this->reglas as $regla) {
                $propuesto = $regla->nivelPara($alergia);
                if ($propuesto !== null) {
                    $nivel = NivelAlerta::mayor($nivel, $propuesto);
                }
            }
            $detalle[] = ['alergia' => $alergia, 'nivel' => $nivel];
            $general = NivelAlerta::mayor($general, $nivel);
        }
        usort($detalle, fn ($a, $b) => $b['nivel']->peso() <=> $a['nivel']->peso()
            ?: strcmp($a['alergia']->alergeno->clave(), $b['alergia']->alergeno->clave()));
        return new AlertaExpediente($codigoPaciente, $general, $detalle, self::huella($activas));
    }

    /** RN-09: cambia si cambia el conjunto de alergias activas o su severidad. */
    public static function huella(array $activas): string
    {
        $partes = array_map(fn (Alergia $a) => $a->id . ':' . $a->severidad->value, $activas);
        sort($partes);
        return substr(hash('sha256', implode('|', $partes)), 0, 16);
    }
}

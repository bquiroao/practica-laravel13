<?php
declare(strict_types=1);

// DESPUÉS: cerrado a modificación. Agregar una regla no toca este archivo.
final class CalculadorAlerta
{
    private const ORDEN = ['SIN_REGISTRO' => 0, 'INFORMATIVA' => 1, 'MODERADA' => 2, 'ALTA' => 3, 'CRITICA' => 4];

    /** @var list<ReglaNivelAlerta> */
    private array $reglas;

    public function __construct(ReglaNivelAlerta ...$reglas)
    {
        $this->reglas = $reglas;
    }

    /** @param list<array{severidad: string, reaccion: ?string}> $alergias */
    public function calcular(array $alergias): string
    {
        $nivel = 'SIN_REGISTRO';
        foreach ($alergias as $alergia) {
            foreach ($this->reglas as $regla) {
                $propuesto = $regla->nivelPara($alergia);
                if ($propuesto !== null && self::ORDEN[$propuesto] > self::ORDEN[$nivel]) {
                    $nivel = $propuesto;
                }
            }
        }
        return $nivel;
    }
}

<?php
declare(strict_types=1);

// ANTES: el calculador conoce cada regla. La de anafilaxia se agregó EDITANDO este método.
final class CalculadorAlertaAntes
{
    /** @param list<array{severidad: string, reaccion: ?string}> $alergias */
    public function calcular(array $alergias): string
    {
        $orden = ['SIN_REGISTRO' => 0, 'INFORMATIVA' => 1, 'MODERADA' => 2, 'ALTA' => 3, 'CRITICA' => 4];
        $nivel = 'SIN_REGISTRO';
        foreach ($alergias as $a) {
            switch ($a['severidad']) {
                case 'life_threatening': $propuesto = 'CRITICA'; break;
                case 'severe':
                case 'unknown': $propuesto = 'ALTA'; break;
                case 'moderate': $propuesto = 'MODERADA'; break;
                default: $propuesto = 'INFORMATIVA';
            }
            // Cambio pedido después: «una reacción anafiláctica siempre es CRÍTICA».
            if (str_contains(mb_strtolower($a['reaccion'] ?? ''), 'anafila')) {
                $propuesto = 'CRITICA';
            }
            if ($orden[$propuesto] > $orden[$nivel]) {
                $nivel = $propuesto;
            }
        }
        return $nivel;
    }
}

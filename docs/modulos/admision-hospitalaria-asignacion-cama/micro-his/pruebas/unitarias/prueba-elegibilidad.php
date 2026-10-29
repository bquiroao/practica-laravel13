<?php
declare(strict_types=1);

use Admision\Domain\Elegibilidad\CriterioElegibilidadCama;
use Admision\Domain\Elegibilidad\EvaluadorElegibilidad;
use Admision\Domain\Modelo\CamaDisponible;
use Admision\Domain\Modelo\Requerimiento;
use Pruebas\Afirmar;
use Pruebas\Datos;

$general = Requerimiento::desde('MEDICINA_INTERNA', 'GENERAL', false);
$elegibles = fn (Requerimiento $r, string $hospital = 'HOSP-01') => array_values(array_map(
    fn ($c) => $c->codigo,
    array_filter(Datos::camas(), fn ($c) => $c->tenantId->valor === $hospital && Datos::evaluador()->motivosDeRechazo($c, $r) === []),
));

return [
    'Medicina interna general: solo B-201 y B-202 (CA-05)' => function () use ($general, $elegibles) {
        Afirmar::igual(['B-201', 'B-202'], $elegibles($general));
    },
    'Con aislamiento: solo B-210 (CA-06)' => function () use ($elegibles) {
        Afirmar::igual(['B-210'], $elegibles(Requerimiento::desde('MEDICINA_INTERNA', 'GENERAL', true)));
    },
    'Cada rechazo trae su motivo (RN-03, RN-04)' => function () use ($general) {
        $motivos = fn (string $codigo) => Datos::evaluador()->motivosDeRechazo(Datos::cama($codigo), $general);
        Afirmar::igual(['La cama no está operativa.'], $motivos('B-203'));
        Afirmar::igual(['La cama de aislamiento se reserva para quien la requiere.'], $motivos('B-210'));
        Afirmar::igual(['La cama es de otro servicio.'], $motivos('C-101'));
        Afirmar::igual(['La cama es de otro servicio.', 'El tipo de cama no coincide.'], $motivos('U-01'));
    },
    'OCP: un criterio nuevo se agrega sin modificar el evaluador (RNF-06)' => function () use ($general) {
        $reservada = new class implements CriterioElegibilidadCama {
            public function motivoDeRechazo(CamaDisponible $cama, Requerimiento $req): ?string
            {
                return $cama->codigo === 'B-202' ? 'La cama está reservada.' : null;
            }
        };
        $evaluador = new EvaluadorElegibilidad(...[...Datos::criterios(), $reservada]);
        Afirmar::igual(['La cama está reservada.'], $evaluador->motivosDeRechazo(Datos::cama('B-202'), $general));
        Afirmar::igual([], $evaluador->motivosDeRechazo(Datos::cama('B-201'), $general));
    },
];

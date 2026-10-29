<?php
declare(strict_types=1);

// Semana 15 — pruebas UNITARIAS de reglas con valores límite (U-01 a U-04).

use Admision\Domain\Excepciones\CamaNoElegible;
use Admision\Domain\Excepciones\DatoInvalido;
use Admision\Domain\Modelo\CodigoPaciente;
use Admision\Domain\Modelo\Requerimiento;
use Admision\Domain\Modelo\TenantId;
use Pruebas\Afirmar;
use Pruebas\Datos;

return [
    'U-01 Requerimiento: servicio de 3 y 40 letras válido; de 2 y 41, no (RF-03)' => function () {
        Requerimiento::desde('UCI', 'UCI', false);
        Requerimiento::desde(str_repeat('A', 40), 'GENERAL', false);
        Afirmar::lanza(DatoInvalido::class, fn () => Requerimiento::desde('AB', 'GENERAL', false));
        Afirmar::lanza(DatoInvalido::class, fn () => Requerimiento::desde(str_repeat('A', 41), 'GENERAL', false));
    },
    'U-02 CodigoPaciente: PAC-00000 y PAC-99999 son límites válidos; PAC-0000 y PAC-000000 no (RF-02)' => function () {
        Afirmar::igual(['PAC-00000', 'PAC-99999'], [CodigoPaciente::desde('PAC-00000')->valor, CodigoPaciente::desde('PAC-99999')->valor]);
        Afirmar::lanza(DatoInvalido::class, fn () => CodigoPaciente::desde('PAC-0000'));
        Afirmar::lanza(DatoInvalido::class, fn () => CodigoPaciente::desde('PAC-000000'));
    },
    'U-03 Admision::abrir rechaza una cama no operativa (RN-04)' => function () {
        $e = Afirmar::lanza(CamaNoElegible::class, fn () => Datos::admision('a0000000-0000-4000-8000-000000000001', 'PAC-00451', Datos::cama('B-203')));
        Afirmar::igual(['La cama no está operativa.'], $e->motivos);
    },
    'U-04 TenantId: HOSP-00 y HOSP-99 válidos; HOSP-100 y HOSP-1 no (RNF-02)' => function () {
        TenantId::desde('HOSP-00');
        TenantId::desde('HOSP-99');
        Afirmar::lanza(DatoInvalido::class, fn () => TenantId::desde('HOSP-100'));
        Afirmar::lanza(DatoInvalido::class, fn () => TenantId::desde('HOSP-1'));
    },
];

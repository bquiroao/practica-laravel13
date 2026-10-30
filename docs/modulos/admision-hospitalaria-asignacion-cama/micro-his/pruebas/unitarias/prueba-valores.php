<?php
declare(strict_types=1);

use Admision\Domain\Excepciones\DatoInvalido;
use Admision\Domain\Modelo\CodigoPaciente;
use Admision\Domain\Modelo\Requerimiento;
use Admision\Domain\Modelo\TenantId;
use Admision\Domain\Modelo\TipoCama;
use Pruebas\Afirmar;

return [
    'CodigoPaciente acepta PAC-00451 y rechaza formatos inválidos (CA-02)' => function () {
        Afirmar::igual('PAC-00451', CodigoPaciente::desde('PAC-00451')->valor);
        foreach (['PAC-451', 'pac-00451', 'PAC-004510', "PAC-00451\n", ''] as $malo) {
            Afirmar::lanza(DatoInvalido::class, fn () => CodigoPaciente::desde($malo));
        }
    },
    'TenantId acepta HOSP-01 y compara por valor' => function () {
        Afirmar::verdadero(TenantId::desde('HOSP-01')->esIgualA(TenantId::desde('HOSP-01')), 'mismo hospital');
        Afirmar::verdadero(!TenantId::desde('HOSP-01')->esIgualA(TenantId::desde('HOSP-02')), 'distinto hospital');
        Afirmar::lanza(DatoInvalido::class, fn () => TenantId::desde('hosp-1'));
    },
    'Requerimiento valida servicio y tipo de cama' => function () {
        $r = Requerimiento::desde('MEDICINA_INTERNA', 'GENERAL', true);
        Afirmar::igual(TipoCama::GENERAL, $r->tipo);
        Afirmar::verdadero($r->requiereAislamiento, 'conserva el aislamiento');
        Afirmar::lanza(DatoInvalido::class, fn () => Requerimiento::desde('MEDICINA_INTERNA', 'CAMILLA', false));
        Afirmar::lanza(DatoInvalido::class, fn () => Requerimiento::desde('medicina interna', 'GENERAL', false));
    },
];

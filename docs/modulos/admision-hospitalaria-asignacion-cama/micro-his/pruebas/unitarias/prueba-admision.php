<?php
declare(strict_types=1);

use Admision\Domain\Excepciones\CamaNoElegible;
use Admision\Domain\Excepciones\CamaNoEncontrada;
use Admision\Domain\Modelo\Admision;
use Admision\Domain\Modelo\CamaDisponible;
use Admision\Domain\Modelo\CodigoPaciente;
use Admision\Domain\Modelo\OrigenAdmision;
use Admision\Domain\Modelo\Requerimiento;
use Admision\Domain\Modelo\Responsable;
use Admision\Domain\Modelo\Rol;
use Admision\Domain\Modelo\TenantId;
use Pruebas\Afirmar;
use Pruebas\Datos;

$abrir = fn (CamaDisponible $cama, bool $aislamiento = false) => Admision::abrir(
    'a0000000-0000-4000-8000-000000000001',
    TenantId::desde('HOSP-01'),
    CodigoPaciente::desde('PAC-00451'),
    Requerimiento::desde('MEDICINA_INTERNA', 'GENERAL', $aislamiento),
    OrigenAdmision::EMERGENCIA,
    $cama,
    Datos::evaluador(),
    new Responsable('usr-adm-01', Rol::ADMISIONISTA, 'sol-prueba-0001'),
    new DateTimeImmutable('2026-03-02T08:15:00+00:00'),
);

return [
    'Admision::abrir deja la admisión ACTIVA con dos eventos de trazabilidad (RN-05)' => function () use ($abrir) {
        $a = $abrir(Datos::cama('B-201'));
        Afirmar::igual('ACTIVA', $a->estado->value);
        Afirmar::igual('B-201', $a->camaCodigo);
        $eventos = $a->eventosPendientes();
        Afirmar::igual(['ADMISION_CREADA', 'CAMA_ASIGNADA'], array_map(fn ($e) => $e->tipo->value, $eventos));
        Afirmar::igual('usr-adm-01', $eventos[1]->responsable->usuarioId);
        Afirmar::igual('sol-prueba-0001', $eventos[1]->responsable->solicitudId);
        Afirmar::igual('B-201', $eventos[1]->detalle['cama_codigo']);
    },
    'Regla de dominio: cama de otro hospital se trata como inexistente (RN-04, CA-09)' => function () use ($abrir) {
        Afirmar::lanza(CamaNoEncontrada::class, fn () => $abrir(Datos::cama('B-201', 'HOSP-02')));
    },
    'Regla de dominio: cama no compatible se rechaza con sus motivos (RN-03)' => function () use ($abrir) {
        $e = Afirmar::lanza(CamaNoElegible::class, fn () => $abrir(Datos::cama('C-101')));
        Afirmar::igual(['La cama es de otro servicio.'], $e->motivos);
        $e = Afirmar::lanza(CamaNoElegible::class, fn () => $abrir(Datos::cama('B-201'), true));
        Afirmar::igual(['El paciente requiere una cama de aislamiento.'], $e->motivos);
    },
    'Una admisión reconstituida no trae eventos pendientes' => function () use ($abrir) {
        $a = $abrir(Datos::cama('B-201'));
        $r = Admision::reconstituir($a->id, $a->tenantId, $a->codigoPaciente, $a->requerimiento, $a->origen, $a->estado, $a->camaId, $a->camaCodigo, $a->creadaPor, $a->creadaEn);
        Afirmar::igual([], $r->eventosPendientes());
    },
];

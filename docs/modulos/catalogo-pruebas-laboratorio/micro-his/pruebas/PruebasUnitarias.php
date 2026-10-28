<?php
declare(strict_types=1);

// Semana 15 — Pruebas unitarias de las reglas del dominio (sin base de datos).
use Lab\Domain\DatosInvalidos;
use Lab\Domain\LabTest;
use Lab\Domain\RequisitoMuestra;
use Lab\Domain\Vigencia;

$d = static fn (string $f): DateTimeImmutable => new DateTimeImmutable($f);

return [
    'U-01 Vigencia::incluye usa límites inclusivos (RN-06)' => function () use ($d): void {
        $v = Vigencia::reconstruir('2027-01-04', '2027-01-31');
        afirmar($v->incluye($d('2027-01-04')) && $v->incluye($d('2027-01-31')), 'desde y hasta deben estar incluidos');
        afirmar(!$v->incluye($d('2027-01-03')) && !$v->incluye($d('2027-02-01')), 'el día antes y el día después no');
        afirmar(Vigencia::reconstruir('2027-01-04', null)->incluye($d('2099-12-31')), 'sin hasta es indefinida');
    },
    'U-02 Vigencia::seSuperponeCon distingue vigencias abiertas, cerradas y contiguas (RN-05)' => function (): void {
        $abierta = Vigencia::reconstruir('2027-01-04', null);
        afirmar($abierta->seSuperponeCon(Vigencia::reconstruir('2027-02-01', null)), 'abierta se superpone con una posterior');
        $cerrada = Vigencia::reconstruir('2027-01-04', '2027-01-31');
        afirmar(!$cerrada->seSuperponeCon(Vigencia::reconstruir('2027-02-01', null)), 'contiguas no se superponen');
        afirmar($cerrada->seSuperponeCon(Vigencia::reconstruir('2027-01-31', null)), 'compartir un día sí se superpone');
    },
    'U-03 RequisitoMuestra::errores acepta datos válidos y marca ayuno negativo y estabilidad 0 (RN-03)' => function (): void {
        afirmar(RequisitoMuestra::errores(datos('glu-001-v1')) === [], 'GLU-001 v1 es válido');
        $e = RequisitoMuestra::errores(datos('glu-001-v1', ['horas_ayuno' => -1, 'estabilidad_horas' => 0, 'tipo_muestra' => 'saliva']));
        afirmar(array_keys($e) === ['tipo_muestra', 'horas_ayuno', 'estabilidad_horas'], 'Errores: ' . implode(',', array_keys($e)));
    },
    'U-04 LabTest::validar exige el código AAA-000 en mayúsculas (RN-02)' => function (): void {
        $hoy = new DateTimeImmutable('2027-01-02');
        LabTest::validar(datos('hem-001'), $hoy);
        foreach (['hem-001', 'HEM-01', 'HEMA-001', 'HEM001'] as $codigo) {
            $e = esperarExcepcion(DatosInvalidos::class, fn () => LabTest::validar(datos('hem-001', ['codigo' => $codigo]), $hoy));
            afirmar(array_keys($e->errores) === ['codigo'], "$codigo debe fallar solo en código");
        }
    },
];

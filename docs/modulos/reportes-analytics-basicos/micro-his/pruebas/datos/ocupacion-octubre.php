<?php
declare(strict_types=1);

// Los mismos datos ficticios de database/datos.sql, en memoria, para el adaptador InMemory.
use MicroHis\Persistence\OcupacionInMemoryRepository;

$camas = [];
foreach (['CIR' => ['Cirugía', 18, 18, 14], 'MED' => ['Medicina', 22, 24, 18], 'PED' => ['Pediatría', 10, 10, 7]] as $codigo => [$sala, $habilitadas, $total]) {
    for ($n = 1; $n <= $total; $n++) {
        $camas[sprintf('%s-%02d', $codigo, $n)] = ['sala' => $sala, 'habilitada' => $n <= $habilitadas];
    }
}

$estancias = [];
$n = 0;
foreach (['CIR' => 14, 'MED' => 18, 'PED' => 7] as $codigo => $ocupadas) {   // mismo orden que row_number() por código
    for ($i = 1; $i <= $ocupadas; $i++) {
        $n++;
        $ingreso = new DateTimeImmutable('2026-10-01');
        $estancias[] = [
            'cama' => sprintf('%s-%02d', $codigo, $i),
            'paciente' => sprintf('PAC-%05d', 450 + $n),
            'ingreso' => $ingreso->modify('+' . ($n % 20) . ' days')->format('Y-m-d'),
            'egreso' => $n % 3 === 0 ? $ingreso->modify('+' . ($n % 20 + 4) . ' days')->format('Y-m-d') : null,
        ];
    }
}
$estancias[] = ['cama' => 'MED-19', 'paciente' => 'PAC-00490', 'ingreso' => '2026-09-05', 'egreso' => '2026-09-20'];
$estancias[] = ['cama' => 'MED-23', 'paciente' => 'PAC-00491', 'ingreso' => '2026-10-03', 'egreso' => null];

return new OcupacionInMemoryRepository($camas, $estancias);

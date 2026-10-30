<?php

declare(strict_types=1);

require_once __DIR__ . '/escenarios.php';

prueba('CAR-01', 'Caracterización: los escenarios de «registrar resultado» responden EXACTAMENTE lo grabado antes del refactor de la semana 7', function (): void {
    $esperado = json_decode((string) file_get_contents(__DIR__ . '/esperado.json'), true, 64, JSON_THROW_ON_ERROR);
    $actual = json_decode((string) json_encode(caracterizar(), JSON_PRESERVE_ZERO_FRACTION), true);
    igual(count($esperado), count($actual), 'cantidad de escenarios');
    foreach ($esperado as $nombre => $observado) {
        igual($observado, $actual[$nombre] ?? null, "escenario «{$nombre}»");
    }
});

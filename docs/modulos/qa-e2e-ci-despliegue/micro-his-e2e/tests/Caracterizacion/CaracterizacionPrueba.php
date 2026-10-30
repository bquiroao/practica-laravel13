<?php

declare(strict_types=1);

prueba('CAR-01', 'Caracterización (semana 7): los 35 escenarios de «reportar» y «desplegar» responden EXACTAMENTE lo grabado antes del refactor', function (): void {
    $esperado = json_decode((string) file_get_contents(__DIR__ . '/esperado.json'), true);
    $distintos = [];
    foreach (require __DIR__ . '/escenarios.php' as $nombre => $escenario) {
        if (json_encode($escenario()) !== json_encode($esperado[$nombre] ?? null)) {
            $distintos[] = $nombre;
        }
    }
    igual([35, []], [count($esperado), $distintos], 'escenarios que cambiaron');
});

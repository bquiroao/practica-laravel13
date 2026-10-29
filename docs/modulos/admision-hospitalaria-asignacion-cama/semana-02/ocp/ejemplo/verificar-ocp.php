<?php
declare(strict_types=1);

// Evidencia ejecutable de OCP. Uso, desde esta carpeta:
//   php verificar-ocp.php [--evidencia=ruta.txt]
// Termina con código 0 si todas las verificaciones pasan y 1 si alguna falla.
// Con --evidencia guarda la misma salida en UTF-8 (sin usar > de PowerShell).

require __DIR__ . '/antes/AsignadorCamas.php';
require __DIR__ . '/despues/elegibilidad.php';

use OcpDespues as D;

// Camas ficticias de HOSP-01 (mismos códigos que la semana 1).
$camas = [
    ['codigo' => 'B-201', 'servicio' => 'MEDICINA_INTERNA', 'tipo' => 'GENERAL', 'aislamiento' => false],
    ['codigo' => 'B-202', 'servicio' => 'MEDICINA_INTERNA', 'tipo' => 'GENERAL', 'aislamiento' => false],
    ['codigo' => 'B-210', 'servicio' => 'MEDICINA_INTERNA', 'tipo' => 'GENERAL', 'aislamiento' => true],
    ['codigo' => 'C-101', 'servicio' => 'CIRUGIA', 'tipo' => 'GENERAL', 'aislamiento' => false],
    ['codigo' => 'U-01', 'servicio' => 'UCI', 'tipo' => 'UCI', 'aislamiento' => false],
];
$requerimientos = [
    'Medicina interna general' => ['servicio' => 'MEDICINA_INTERNA', 'tipo' => 'GENERAL', 'requiereAislamiento' => false],
    'Medicina interna con aislamiento' => ['servicio' => 'MEDICINA_INTERNA', 'tipo' => 'GENERAL', 'requiereAislamiento' => true],
    'Cirugía general' => ['servicio' => 'CIRUGIA', 'tipo' => 'GENERAL', 'requiereAislamiento' => false],
    'UCI' => ['servicio' => 'UCI', 'tipo' => 'UCI', 'requiereAislamiento' => false],
];

$rutaEvidencia = null;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--evidencia=')) {
        $rutaEvidencia = substr($arg, strlen('--evidencia='));
    }
}
ob_start();

$fallas = 0;
$verificar = function (string $nombre, bool $ok) use (&$fallas): void {
    echo ($ok ? '[OK]    ' : '[FALLA] ') . $nombre . PHP_EOL;
    if (!$ok) {
        $fallas++;
    }
};

$antes = new \OcpAntes\AsignadorCamas();
$evaluador = new D\EvaluadorElegibilidad(new D\CriterioServicio(), new D\CriterioTipoCama(), new D\CriterioAislamiento());
$aObjeto = fn (array $c) => new D\CamaDisponible($c['codigo'], $c['servicio'], $c['tipo'], $c['aislamiento']);
$aReq = fn (array $r) => new D\Requerimiento($r['servicio'], $r['tipo'], $r['requiereAislamiento']);

// 1. El diseño nuevo da exactamente el mismo resultado que el anterior.
foreach ($requerimientos as $nombre => $r) {
    $deAntes = array_values(array_map(fn ($c) => $c['codigo'], array_filter($camas, fn ($c) => $antes->esElegible($c, $r))));
    $deDespues = array_values(array_map(fn ($c) => $c['codigo'], array_filter($camas, fn ($c) => $evaluador->motivosDeRechazo($aObjeto($c), $aReq($r)) === [])));
    $verificar("Mismo resultado antes/después para «{$nombre}»: " . implode(', ', $deDespues), $deAntes === $deDespues);
}

// 2. Cada rechazo explica su motivo (el diseño anterior solo devolvía false).
$motivos = $evaluador->motivosDeRechazo($aObjeto($camas[0]), $aReq($requerimientos['Medicina interna con aislamiento']));
$verificar('B-201 rechazada con motivo: ' . implode(' ', $motivos), $motivos === ['El paciente requiere una cama de aislamiento.']);

// 3. Extensión sin modificar el evaluador: un criterio nuevo definido aquí mismo.
$criterioNoReservada = new class (['B-202']) implements D\CriterioElegibilidadCama {
    public function __construct(private array $reservadas)
    {
    }
    public function motivoDeRechazo(D\CamaDisponible $cama, D\Requerimiento $req): ?string
    {
        return in_array($cama->codigo, $this->reservadas, true) ? 'La cama está reservada.' : null;
    }
};
$extendido = new D\EvaluadorElegibilidad(new D\CriterioServicio(), new D\CriterioTipoCama(), new D\CriterioAislamiento(), $criterioNoReservada);
$elegibles = array_values(array_map(fn ($c) => $c['codigo'], array_filter($camas, fn ($c) => $extendido->motivosDeRechazo($aObjeto($c), $aReq($requerimientos['Medicina interna general'])) === [])));
$verificar('Criterio nuevo agregado sin modificar EvaluadorElegibilidad: ' . implode(', ', $elegibles), $elegibles === ['B-201']);

echo PHP_EOL . ($fallas === 0 ? 'Resultado: todas las verificaciones pasaron.' : "Resultado: {$fallas} verificación(es) fallaron.") . PHP_EOL;

$salida = (string) ob_get_clean();
echo $salida;
if ($rutaEvidencia !== null) {
    $carpeta = dirname($rutaEvidencia);
    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0777, true);
    }
    file_put_contents($rutaEvidencia, $salida);
    echo "Evidencia guardada en {$rutaEvidencia}" . PHP_EOL;
}
exit($fallas === 0 ? 0 : 1);

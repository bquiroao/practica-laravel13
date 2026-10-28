<?php
declare(strict_types=1);

// Uso (desde semana-02/solid-srp/): php verificar.php --evidencia=evidencia/salida-verificar.txt
// Comprueba que el diseño "después" se comporta igual que el "antes" en los mismos escenarios.
require __DIR__ . '/antes/CatalogoPruebasService.php';
foreach (['Requisitos', 'PoliticaVersionado', 'RepositorioPruebas', 'RegistrarPrueba'] as $c) {
    require __DIR__ . "/despues/$c.php";
}

$hoy = '2027-01-02';
$escenarios = [
    'Alta de GLU-001 v1' => ['codigo' => 'GLU-001', 'volumen_minimo_ml' => 2, 'vigente_desde' => '2027-01-04'],
    'Misma fecha desde (RN-05)' => ['codigo' => 'GLU-001', 'volumen_minimo_ml' => 2, 'vigente_desde' => '2027-01-04'],
    'Versión 2 desde 2027-02-01' => ['codigo' => 'GLU-001', 'volumen_minimo_ml' => 1, 'vigente_desde' => '2027-02-01'],
    'Volumen 0 (RN-03)' => ['codigo' => 'HEM-001', 'volumen_minimo_ml' => 0, 'vigente_desde' => '2027-01-04'],
];

$antes = new Antes\CatalogoPruebasService();
$repo = new Despues\RepositorioEnMemoria();
$despues = new Despues\RegistrarPrueba($repo);
$lineas = ['Verificación SRP — antes vs después — ' . date('Y-m-d H:i:s'), ''];
$iguales = 0;
foreach ($escenarios as $nombre => $datos) {
    $a = $antes->registrar($datos, $hoy);
    try {
        $v = $despues->ejecutar($datos, $hoy);
        $d = "OK: {$v['code']} versión {$v['version']} registrada";
    } catch (Throwable $e) {
        $d = 'ERROR: ' . $e->getMessage();
    }
    $igual = $a === $d;
    $iguales += $igual ? 1 : 0;
    $lineas[] = sprintf('%-28s antes="%s" | después="%s" | %s', $nombre, $a, $d, $igual ? 'IGUAL' : 'DISTINTO');
}
$cierreIgual = $antes->tabla[0]['valid_to'] === $repo->tabla[0]['valid_to'];
$lineas[] = '';
$lineas[] = 'Cierre de la versión 1: antes=' . $antes->tabla[0]['valid_to'] . ' | después=' . $repo->tabla[0]['valid_to'];
$lineas[] = sprintf('Resultado: %d de %d escenarios iguales; cierre %s', $iguales, count($escenarios), $cierreIgual ? 'igual' : 'distinto');

$texto = implode(PHP_EOL, $lineas) . PHP_EOL;
echo $texto;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--evidencia=')) {
        $ruta = substr($arg, 12);
        if (!is_dir(dirname($ruta))) {
            mkdir(dirname($ruta), 0777, true);
        }
        file_put_contents($ruta, $texto);
        echo "Evidencia guardada en $ruta" . PHP_EOL;
    }
}

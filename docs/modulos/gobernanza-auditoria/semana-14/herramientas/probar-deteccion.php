<?php

declare(strict_types=1);

/**
 * Semana 14 · ¿Las métricas del plan SQA detectan defectos reales? Copia el Micro-HIS, inyecta UN defecto por copia,
 * mide con medir-sqa.php (SQA_MICROHIS=copia, --sin-mutaciones) e informa qué métrica pasó a ROJO o ÁMBAR.
 * Uso: php herramientas/probar-deteccion.php   (desde docs/modulos/gobernanza-auditoria/semana-14)
 */
$micro = dirname(__DIR__, 2) . '/micro-his-auditoria';
$defectos = [
    'D1 · fuga entre tenants en el detalle' => ['src/Dominio/Modelo/Alcance.php',
        'return $this->esRed() || $m->hospital === $this->hospital;', 'return true;'],
    'D2 · la cadena salta una secuencia al anexar' => ['src/Aplicacion/Servicios/CadenaSellada.php',
        '$secuencia = ($anterior?->secuencia ?? 0) + 1;', '$secuencia = ($anterior?->secuencia ?? 0) + (random_int(1, 50) === 1 ? 2 : 1);'],
    'D3 · el contrato agrega un código de error que ninguna prueba afirma' => ['api/openapi.yaml',
        'DATOS_INVALIDOS, FILTRO_NO_SOPORTADO, YA_CORREGIDO,', 'DATOS_INVALIDOS, FILTRO_NO_SOPORTADO, YA_CORREGIDO, LIMITE_EXCEDIDO,'],
    'D4 · un emisor puede registrar en otro hospital' => ['src/Presentacion/Http/Identidad.php',
        "return \$this->hospital === '*' || \$this->hospital === \$hospital;", 'return true;'],
];

$copiar = static function (string $de, string $a) use (&$copiar): void {
    @mkdir($a, 0777, true);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($de, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $item) {
        $destino = $a . substr($item->getPathname(), strlen($de));
        $item->isDir() ? @mkdir($destino, 0777, true) : copy($item->getPathname(), $destino);
    }
};
$borrar = static function (string $dir): void {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
};

$sinDetectar = 0;
foreach ($defectos as $nombre => [$archivo, $buscar, $reemplazo]) {
    $copia = sys_get_temp_dir() . '/sqa-auditoria-' . bin2hex(random_bytes(3));
    $copiar($micro, $copia);
    $codigo = (string) file_get_contents("$copia/$archivo");
    if (substr_count($codigo, $buscar) !== 1) {
        echo "$nombre: NO SE PUDO INYECTAR\n";
        $sinDetectar++;
        $borrar($copia);
        continue;
    }
    file_put_contents("$copia/$archivo", str_replace($buscar, $reemplazo, $codigo));
    $salida = (string) shell_exec('set SQA_MICROHIS=' . $copia . '&& php ' . escapeshellarg(__DIR__ . '/medir-sqa.php') . ' --sin-mutaciones 2>NUL');
    if (PHP_OS_FAMILY !== 'Windows') {
        $salida = (string) shell_exec('SQA_MICROHIS=' . escapeshellarg($copia) . ' php ' . escapeshellarg(__DIR__ . '/medir-sqa.php') . ' --sin-mutaciones 2>/dev/null');
    }
    preg_match_all('/^(ROJO|ÁMBAR)\s+(M\d)\s+(.+)$/mu', $salida, $m, PREG_SET_ORDER);
    $alertas = array_values(array_filter($m, static fn (array $x): bool => !in_array($x[2], ['M2', 'M6', 'M7'], true))); // M6/M7 ya están en rojo en la línea base; M2 no se mide
    echo PHP_EOL . $nombre . PHP_EOL;
    if ($alertas === []) {
        echo "   NO DETECTADO por ninguna métrica\n";
        $sinDetectar++;
    }
    foreach ($alertas as $x) {
        preg_match('/^' . $x[1] . '\s+' . $x[2] . '.*\n\s+valor: (.*)\n\s+(.*)$/mu', $salida, $d);
        printf("   %-5s %s %s → %s\n", $x[1], $x[2], trim($x[3]), trim(($d[1] ?? '') . ' · ' . ($d[2] ?? '')));
    }
    $borrar($copia);
}
echo PHP_EOL . 'Defectos inyectados: ' . count($defectos) . '  Sin detectar: ' . $sinDetectar . PHP_EOL;
exit($sinDetectar === 0 ? 0 : 1);

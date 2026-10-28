<?php

declare(strict_types=1);

/*
 * ¿Las pruebas de verificar.php detectan defectos reales? Cada mutación introduce UN defecto en una copia temporal
 * del ejemplo y ejecuta verificar.php: la mutación debe hacerlo fallar. Varias reintroducen, a propósito, la violación
 * de LSP del ANTES dentro del DESPUÉS.
 *
 * Uso:  php docs/modulos/qa-regresion-defectos/semana-02/ejemplo-lsp/mutaciones.php
 */

$mutaciones = [
    'M1 la huella deja de ser final (un subtipo podría sobrescribirla, como EnlaceExterno)' => ['despues/src/Dominio/Evidencia/EvidenciaInmutable.php',
        'final public function huella(): string', 'public function huella(): string'],
    'M2 una captura con datos clínicos lanza al leer su contenido (como en el ANTES)' => ['despues/src/Dominio/Evidencia/EvidenciaInmutable.php',
        "    final public function contenido(): string\n    {\n        return \$this->bytes;",
        "    final public function contenido(): string\n    {\n        if (\$this->clasificacion === Clasificacion::DATOS_CLINICOS) { throw new \\RuntimeException('datos clínicos'); }\n        return \$this->bytes;"],
    'M3 la huella se calcula sobre otra cosa que el contenido' => ['despues/src/Dominio/Evidencia/EvidenciaInmutable.php',
        "\$this->huella = hash('sha256', \$bytes);", "\$this->huella = hash('sha256', \$bytes . 'v1');"],
    'M4 una referencia externa cuenta como evidencia' => ['despues/src/Aplicacion/Aplicacion.php',
        'if ($resultado->exigeEvidencia() && $evidencias === []) {', 'if ($resultado->exigeEvidencia() && $evidencias === [] && $referencias === []) {'],
    'M5 con el almacén caído se registra igual (fail-open)' => ['despues/src/Aplicacion/Aplicacion.php',
        "return new Respuesta(503, 'EVIDENCIA_NO_DISPONIBLE');", '$guardadas = [];'],
    'M6 ver una evidencia clínica no exige el permiso especial' => ['despues/src/Aplicacion/Aplicacion.php',
        "Clasificacion::DATOS_CLINICOS ? 'qa.ver_evidencia_clinica' : 'qa.consultar'", "Clasificacion::DATOS_CLINICOS ? 'qa.consultar' : 'qa.consultar'"],
    'M7 una FALLA no exige defecto' => ['despues/src/Aplicacion/Aplicacion.php',
        'if ($resultado === Resultado::FALLA && $defecto === null) {', 'if (false) {'],
    'M8 el autor puede aprobar su propio caso' => ['despues/src/Dominio/Modelo.php',
        'if ($aprobador === $this->autor) {', 'if (false) {'],
    'M9 el alcance no exige los casos críticos de la matriz' => ['despues/src/Dominio/Modelo.php',
        '$faltan = array_values(array_diff($criticosAfectados, array_keys($this->alcance)));', '$faltan = [];'],
    'M10 el desarrollador puede cerrar el defecto al corregirlo' => ['despues/src/Dominio/Modelo.php',
        '$this->estado = EstadoDefecto::CORREGIDO;', '$this->estado = EstadoDefecto::CERRADO;'],
    'M11 el criterio de salida exige superar el umbral (> en vez de ≥)' => ['despues/src/Dominio/Modelo.php',
        'if ($tasa < $criterio->umbral) {', 'if ($tasa <= $criterio->umbral) {'],
    'M12 cuenta el PRIMER intento de cada caso, no el último' => ['despues/src/Dominio/Modelo.php',
        "            if (\$i->casoId === \$casoId) {\n                \$ultimo = \$i;\n            }",
        "            if (\$i->casoId === \$casoId && \$ultimo === null) {\n                \$ultimo = \$i;\n            }"],
    'M13 sin idempotencia' => ['despues/src/Aplicacion/Aplicacion.php',
        'if (isset($this->claves[$clave])) {', 'if (false) {'],
    'M14 una captura acepta cualquier archivo' => ['despues/src/Dominio/Evidencia/Tipos.php',
        "if (!str_starts_with(\$bytes, \"\\x89PNG\\r\\n\\x1a\\n\") && !str_starts_with(\$bytes, \"\\xFF\\xD8\\xFF\")) {", 'if (false) {'],
    'M15 el informe lee el contenido de las evidencias' => ['despues/src/Aplicacion/Aplicacion.php',
        "(\$e['clasificacion'] === 'DATOS_CLINICOS' ? ' [clínica]' : '')", "(\$e['clasificacion'] === 'DATOS_CLINICOS' ? ' [clínica]' : '') . ' IHDR'"],
];

$origen = __DIR__;
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

$detectadas = 0;
foreach ($mutaciones as $nombre => [$archivo, $buscar, $reemplazo]) {
    $copia = sys_get_temp_dir() . '/qa-lsp-' . bin2hex(random_bytes(4));
    $copiar($origen, $copia);
    $codigo = (string) file_get_contents("$copia/$archivo");
    if (substr_count($codigo, $buscar) !== 1) {
        printf("[NO APLICA] %s (el texto a mutar no aparece exactamente una vez)\n", $nombre);
        $borrar($copia);
        continue;
    }
    file_put_contents("$copia/$archivo", str_replace($buscar, $reemplazo, $codigo));
    exec('php ' . escapeshellarg("$copia/verificar.php") . ' 2>&1', $salida, $codigoSalida);
    preg_match_all('/^\[FALLA\]\s+(\S+)/m', implode("\n", $salida), $m);
    $salida = [];
    $detectada = $codigoSalida !== 0;
    $detectadas += $detectada ? 1 : 0;
    printf("[%-9s] %-78s %s\n", $detectada ? 'DETECTA' : 'SOBREVIVE', $nombre, $detectada ? 'falla: ' . implode(', ', $m[1]) : '');
    $borrar($copia);
}
echo str_repeat('-', 100) . PHP_EOL;
printf("Mutaciones: %d  Detectadas: %d  Sobreviven: %d\n", count($mutaciones), $detectadas, count($mutaciones) - $detectadas);
exit($detectadas === count($mutaciones) ? 0 : 1);

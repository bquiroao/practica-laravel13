<?php

declare(strict_types=1);

/*
 * ¿Las pruebas de verificar.php detectan defectos reales? Cada mutación introduce UN defecto
 * en una copia temporal del ejemplo y ejecuta verificar.php: la mutación debe hacerlo fallar.
 *
 * Uso:  php docs/modulos/gobernanza-auditoria/semana-02/ejemplo-ocp/mutaciones.php
 */

$mutaciones = [
    'M1 «hasta» deja de incluir el día completo' => ['despues/src/Filtros/FiltroRangoFechas.php',
        "\$this->fecha(\$p['hasta'])->modify('+1 day')", "\$this->fecha(\$p['hasta'])"],
    'M2 sin idempotencia: el reintento se registra otra vez' => ['despues/src/Servicios/ServicioRegistro.php',
        'if ($existente !== null) {', 'if (false) {'],
    'M3 el sello no depende del anterior (sin cadena)' => ['despues/src/Dominio/Sello.php',
        'hash(\'sha256\', $selloAnterior . $secuencia', 'hash(\'sha256\', $secuencia'],
    'M4 la consulta ignora el hospital (fuga entre tenants)' => ['despues/src/Dominio/ConsultaMovimientos.php',
        'if (!$this->red && $m->hospital !== $this->hospital) {', 'if (false) {'],
    'M5 la consulta no se audita' => ['despues/src/Servicios/ServicioConsulta.php',
        "\$this->auditar(\$consulta->hospital, \$actor, ['alcance'", "if (false) \$this->auditar(\$consulta->hospital, \$actor, ['alcance'"],
    'M6 la regla de tenant se evalúa después de los campos' => ['despues/composicion.php',
        "new ReglaTenantDelEmisor(),\n        new ReglaCamposObligatorios(),", "new ReglaCamposObligatorios(),\n        new ReglaTenantDelEmisor(),"],
    'M7 los datos prohibidos solo se buscan en el primer nivel' => ['despues/src/Reglas/ReglaSinDatosProhibidos.php',
        "(string) json_encode(\$s->datos['detalle'] ?? [])", "implode(',', array_map(static fn (\$k): string => \"\\\"\$k\\\":\", array_keys(\$s->datos['detalle'] ?? [])))"],
    'M8 el registro no pide el permiso auditoria.registrar' => ['despues/src/Servicios/ServicioRegistro.php',
        "if (!\$this->acceso->permite(\$solicitud->emisor", "if (false && !\$this->acceso->permite(\$solicitud->emisor"],
];

$origen = __DIR__;
$detectadas = 0;
foreach ($mutaciones as $nombre => [$archivo, $buscar, $reemplazo]) {
    $copia = sys_get_temp_dir() . '/auditoria-mut-' . getmypid() . '-' . md5($nombre);
    copiar($origen, $copia);
    $codigo = (string) file_get_contents("$copia/$archivo");
    if (!str_contains($codigo, $buscar)) {
        echo "[ERROR ] $nombre: el texto a mutar no está en $archivo" . PHP_EOL;
        continue;
    }
    file_put_contents("$copia/$archivo", str_replace($buscar, $reemplazo, $codigo));
    exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=0 -d log_errors=0 ' . escapeshellarg("$copia/verificar.php"), $salida, $codigoSalida);
    $fallan = array_values(array_filter($salida, static fn (string $l): bool => str_starts_with($l, '[FALLA]')));
    $ids = array_map(static fn (string $l): string => preg_split('/\s+/', $l)[1], $fallan);
    $detectada = $codigoSalida !== 0;
    $detectadas += $detectada ? 1 : 0;
    printf("[%s] %s %s\n", $detectada ? 'DETECTA' : 'SOBREVIVE', mb_str_pad($nombre, 58), $detectada ? 'falla: ' . implode(', ', $ids) : '');
    unset($salida);
    borrar($copia);
}
echo str_repeat('-', 78) . PHP_EOL . sprintf('Mutaciones: %d  Detectadas: %d  Sobreviven: %d', count($mutaciones), $detectadas, count($mutaciones) - $detectadas) . PHP_EOL;
exit($detectadas === count($mutaciones) ? 0 : 1);

function copiar(string $de, string $a): void
{
    @mkdir($a, 0777, true);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($de, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $item) {
        $destino = $a . substr($item->getPathname(), strlen($de));
        $item->isDir() ? @mkdir($destino, 0777, true) : copy($item->getPathname(), $destino);
    }
}

function borrar(string $directorio): void
{
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directorio, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($directorio);
}

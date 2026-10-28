<?php

declare(strict_types=1);

/*
 * ¿Las pruebas detectan defectos reales? Cada mutación introduce UN defecto en una copia temporal del
 * Micro-HIS y ejecuta tests/ejecutar.php: la suite debe fallar. Con QA_DB_DSN corren también las de
 * integración (las mutaciones del SQL y del esquema solo las detectan ellas).
 *   php tests/mutaciones.php
 */

$mutaciones = [
    'M1 el autor puede aprobar su propio caso (R-02)' => ['src/Dominio/Modelo/CasoPrueba.php',
        'if ($aprobador === $this->autor) {', 'if (false) {'],
    'M2 la campaña inicia sin los críticos de la matriz (R-03)' => ['src/Dominio/Modelo/Campana.php',
        "if (\$faltan !== []) {\n            throw new ReglaRechazada('ALCANCE_INCOMPLETO', \$faltan);", "if (false) {\n            throw new ReglaRechazada('ALCANCE_INCOMPLETO', \$faltan);"],
    'M3 una FALLA sin evidencia se acepta (R-05)' => ['src/Aplicacion/CasosDeUso/RegistrarResultado.php',
        'if ($resultado->exigeEvidencia() && $evidencias === []) {', 'if (false) {'],
    'M4 la referencia externa cuenta como evidencia (R-05)' => ['src/Aplicacion/CasosDeUso/RegistrarResultado.php',
        '$evidencias === []) {', '$evidencias === [] && ($datos[\'referencias\'] ?? []) === []) {'],
    'M5 almacén caído: se registra igual sin evidencia (fail-open)' => ['src/Aplicacion/CasosDeUso/RegistrarResultado.php',
        '$e->tamano(), $e->clasificacion()->value, $this->almacen->guardar($tenant, $e)), $evidencias);',
        '$e->tamano(), $e->clasificacion()->value, (function () use ($tenant, $e) { try { return $this->almacen->guardar($tenant, $e); } catch (AlmacenNoDisponible) { return \'pendiente\'; } })()), $evidencias);'],
    'M6 una FALLA sin defecto se acepta (R-06)' => ['src/Aplicacion/CasosDeUso/RegistrarResultado.php',
        "throw new ReglaRechazada('DEFECTO_REQUERIDO');   // R-06", "null;"],
    'M7 cambiar la severidad no exige motivo (R-07)' => ['src/Dominio/Modelo/Defecto.php',
        'if ($severidad !== $this->propuesta && trim((string) $motivo) === \'\') {', 'if (false) {'],
    'M8 el desarrollador cierra el defecto (R-08)' => ['src/Dominio/Modelo/Defecto.php',
        '$this->estado = EstadoDefecto::CORREGIDO;', '$this->estado = EstadoDefecto::CERRADO;'],
    'M9 la decisión cuenta el PRIMER intento, no el último (R-09)' => ['src/Dominio/Modelo/Campana.php',
        "            if (\$i->casoId === \$casoId) {\n                \$ultimo = \$i;", "            if (\$i->casoId === \$casoId && \$ultimo === null) {\n                \$ultimo = \$i;"],
    'M10 GO aunque haya críticos abiertos (R-09)' => ['src/Dominio/Modelo/Campana.php',
        'if ($criticosAbiertos > 0) {', 'if (false) {'],
    'M11 sin idempotencia (R-12)' => ['src/Aplicacion/CasosDeUso/RegistrarResultado.php',
        "if (\$previo !== null) {\n                return \$this->repetido(\$previo, \$huella);", "if (false) {\n                return \$this->repetido(\$previo, \$huella);"],
    'M12 el resultado y la traza en transacciones separadas' => ['src/Aplicacion/CasosDeUso/RegistrarResultado.php',
        "            \$this->auditoria->registrar('RESULTADO_REGISTRADO'", "            try { \$this->auditoria->registrar('RESULTADO_REGISTRADO'"],
    'M13 la consulta de campañas no filtra por tenant (SQL)' => ['src/Persistencia/Pdo/PdoRepositorioCampanas.php',
        'FROM campanas WHERE tenant = ? AND id = ?\', [$tenant, $id]', 'FROM campanas WHERE (tenant = ? OR true) AND id = ?\', [$tenant, $id]'],
    'M14 los intentos se pueden modificar (esquema)' => ['database/schema.sql',
        'CREATE TRIGGER intentos_solo_agregar BEFORE UPDATE OR DELETE ON intentos', 'CREATE TRIGGER intentos_solo_agregar BEFORE TRUNCATE ON intentos FOR EACH STATEMENT EXECUTE FUNCTION solo_agregar();
-- '],
    'M15 la trazabilidad expone el contenido de la evidencia' => ['src/Aplicacion/CasosDeUso/ConsultarTrazabilidad.php',
        "'tamano' => \$e->tamano, 'clasificacion' => \$e->clasificacion]", "'tamano' => \$e->tamano, 'clasificacion' => \$e->clasificacion, 'ubicacion' => 'captura-de-prueba']"],
    'M16 el traductor manda las reglas de datos como 409' => ['src/Presentacion/Http/TraductorSalida.php',
        'Salida::INVALIDO => 422,', 'Salida::INVALIDO => 409,'],
];

$origen = dirname(__DIR__);
$detectadas = 0;
foreach ($mutaciones as $nombre => [$archivo, $buscar, $reemplazo]) {
    $copia = sys_get_temp_dir() . '/micro-qa-mut-' . getmypid() . '-' . md5($nombre);
    copiar($origen, $copia);
    $codigo = str_replace("\r\n", "\n", (string) file_get_contents("$copia/$archivo"));
    if (!str_contains($codigo, $buscar)) {
        echo "[ERROR    ] $nombre: el texto a mutar no está en $archivo" . PHP_EOL;
        borrar($copia);
        continue;
    }
    $mutado = str_replace($buscar, $reemplazo, $codigo);
    if (str_starts_with($nombre, 'M12')) {
        // La traza se traga su propio error: el intento queda confirmado aunque la auditoría falle.
        $mutado = preg_replace('/(\$this->auditoria->registrar\(\'RESULTADO_REGISTRADO\'.*?\);)/', '$1 } catch (\Throwable) {}', $mutado, 1);
    }
    file_put_contents("$copia/$archivo", $mutado);
    exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=0 -d log_errors=0 ' . escapeshellarg("$copia/tests/ejecutar.php"), $salida, $codigoSalida);
    $fallan = array_values(array_filter($salida, static fn (string $l): bool => str_starts_with($l, '[FALLA]')));
    $ids = array_map(static fn (string $l): string => preg_split('/\s+/', $l)[1], $fallan);
    $detectada = $codigoSalida !== 0;
    $detectadas += $detectada ? 1 : 0;
    printf("[%s] %s %s\n", $detectada ? 'DETECTA  ' : 'SOBREVIVE', mb_str_pad($nombre, 64), $detectada ? 'falla: ' . implode(', ', $ids) : '');
    unset($salida);
    borrar($copia);
}
echo str_repeat('-', 100) . PHP_EOL . sprintf('Mutaciones: %d  Detectadas: %d  Sobreviven: %d', count($mutaciones), $detectadas, count($mutaciones) - $detectadas) . PHP_EOL;
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

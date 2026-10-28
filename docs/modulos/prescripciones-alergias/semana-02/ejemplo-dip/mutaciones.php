<?php

declare(strict_types=1);

/*
 * ¿Las pruebas de verificar.php detectan defectos reales? Cada mutación introduce UN defecto
 * en una copia temporal del ejemplo y ejecuta verificar.php: la mutación debe hacerlo fallar.
 * M1 solo se detecta con PostgreSQL (PRESCRIPCIONES_DB_DSN): es un defecto del adaptador PDO.
 *
 * Uso:  php docs/modulos/prescripciones-alergias/semana-02/ejemplo-dip/mutaciones.php
 */

$mutaciones = [
    'M1 el adaptador PDO devuelve vacío si la consulta falla (fail-open)' => ['despues/src/Adaptadores/Pdo/FuenteAlergiasPdo.php',
        <<<'X'
        throw new FuenteAlergiasNoDisponible('El expediente no respondió.', 0, $e);
        X, '$filas = [];'],
    'M2 el caso de uso trata la fuente caída como «sin alergias»' => ['despues/src/Aplicacion/EvaluacionClinica.php',
        '$expediente = $this->alergias->alergiasDe($paciente, $hospital);',
        'try { $expediente = $this->alergias->alergiasDe($paciente, $hospital); } catch (FuenteAlergiasNoDisponible) { $expediente = new ExpedienteAlergias(true, []); }'],
    'M3 la coincidencia directa deja de bloquear' => ['despues/src/Dominio/VerificadorAlergias.php',
        '$a->severidad, true, $a->reaccion', '$a->severidad, false, $a->reaccion'],
    'M4 toda reactividad cruzada se puede justificar' => ['despues/src/Dominio/Alergia.php',
        <<<'X'
        return in_array($this->severidad, ['life_threatening', 'unknown'], true);
        X, 'return false;'],
    'M5 la severidad «unknown» se trata como leve' => ['despues/src/Dominio/Alergia.php',
        <<<'X'
        ['life_threatening', 'unknown']
        X, <<<'X'
        ['life_threatening']
        X],
    'M6 la dosis máxima se duplica' => ['despues/src/Dominio/ValidadorPrescripcion.php',
        '> $m->dosisMaximaDiaria', '> $m->dosisMaximaDiaria * 2'],
    'M7 no se exige confirmar «sin alergias conocidas»' => ['despues/src/Aplicacion/EmitirPrescripcion.php',
        'if (!$expediente->evaluado && !$s->confirmaSinAlergias) {', 'if (false) {'],
    'M8 la advertencia no exige el permiso override' => ['despues/src/Aplicacion/EmitirPrescripcion.php',
        <<<'X'
        || !$this->acceso->permite($s->usuario, 'prescripcion.override', $s->hospital)
        X, '|| false'],
    'M9 sin idempotencia' => ['despues/src/Aplicacion/EmitirPrescripcion.php',
        'if ($previa !== null) {', 'if (false) {'],
    'M10 las alergias resueltas también cuentan' => ['despues/src/Dominio/ExpedienteAlergias.php',
        'static fn (Alergia $a): bool => $a->activa()', 'static fn (Alergia $a): bool => true'],
    'M11 no se verifica el hospital del paciente' => ['despues/src/Aplicacion/EmitirPrescripcion.php',
        'if (!$this->pacientes->existeEnHospital($s->paciente, $s->hospital)) {', 'if (false) {'],
    'M12 el bloqueo no deja traza' => ['despues/src/Aplicacion/EmitirPrescripcion.php',
        <<<'X'
        $this->auditoria->registrar('PRESCRIPCION_BLOQUEADA'
        X, <<<'X'
        if (false) $this->auditoria->registrar('PRESCRIPCION_BLOQUEADA'
        X],
    'M13 regresión de DIP: el caso de uso toma la hora del sistema' => ['despues/src/Aplicacion/EmitirPrescripcion.php',
        '$this->reloj->ahora()', <<<'X'
        new \DateTimeImmutable('now')
        X],
];

$origen = __DIR__;
$detectadas = 0;
foreach ($mutaciones as $nombre => [$archivo, $buscar, $reemplazo]) {
    $copia = sys_get_temp_dir() . '/prescripciones-mut-' . getmypid() . '-' . md5($nombre);
    copiar($origen, $copia);
    $codigo = (string) file_get_contents("$copia/$archivo");
    if (!str_contains($codigo, $buscar)) {
        echo "[ERROR    ] $nombre: el texto a mutar no está en $archivo" . PHP_EOL;
        borrar($copia);
        continue;
    }
    file_put_contents("$copia/$archivo", str_replace($buscar, $reemplazo, $codigo));
    exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=0 -d log_errors=0 ' . escapeshellarg("$copia/verificar.php"), $salida, $codigoSalida);
    $fallan = array_values(array_filter($salida, static fn (string $l): bool => str_starts_with($l, '[FALLA]')));
    $ids = array_map(static fn (string $l): string => preg_split('/\s+/', $l)[1], $fallan);
    $detectada = $codigoSalida !== 0;
    $detectadas += $detectada ? 1 : 0;
    printf("[%s] %s %s\n", $detectada ? 'DETECTA  ' : 'SOBREVIVE', mb_str_pad($nombre, 66), $detectada ? 'falla: ' . implode(', ', $ids) : '');
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

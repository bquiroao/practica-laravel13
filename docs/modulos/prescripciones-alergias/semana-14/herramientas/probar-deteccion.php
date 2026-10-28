<?php

declare(strict_types=1);

/**
 * Semana 14 · ¿Las métricas del plan SQA detectan defectos reales? Copia el Micro-HIS, inyecta UN defecto por copia,
 * mide con medir-sqa.php (SQA_MICROHIS=copia, --sin-mutaciones, sin base) y compara cada VALOR con el de la copia sin defecto.
 * Una métrica detecta el defecto si su valor cambia. Se compara el valor y no el color porque M3, M4 y M7 ya están en
 * ROJO en la línea base: un defecto nuevo no cambiaría el color.
 * Uso: php herramientas/probar-deteccion.php   (desde docs/modulos/prescripciones-alergias/semana-14)
 */
$micro = dirname(__DIR__, 2) . '/micro-his-prescripciones';
$defectos = [
    'D1 · la coincidencia por nombre deja de normalizar (mayúsculas y espacios)' => ['src/Dominio/Reglas/VerificadorAlergias.php',
        'return self::normalizar((string) $a->nombre) === self::normalizar($m->principioActivo);', 'return (string) $a->nombre === $m->principioActivo;'],
    'D2 · el repositorio devuelve prescripciones de otro hospital' => ['src/Persistencia/EnMemoria/RepositorioPrescripcionesEnMemoria.php',
        'return $p !== null && $p->hospital === $hospital ? $this->conSuAnulacion($p) : null;', 'return $p !== null ? $this->conSuAnulacion($p) : null;'],
    'D3 · el Expediente caído se lee como «sin alergias» (fail-open)' => ['src/Aplicacion/Servicios/EvaluacionClinica.php',
        '$expediente = $this->alergias->alergiasDe($paciente, $hospital);',
        'try { $expediente = $this->alergias->alergiasDe($paciente, $hospital); } catch (FuenteAlergiasNoDisponible) { $expediente = new ExpedienteAlergias(true, []); }'],
    'D4 · el contrato agrega un código de error que ninguna prueba afirma' => ['api/openapi.yaml',
        'MOTIVO_REQUERIDO, ESTADO_NO_PERMITE_ANULAR]', 'MOTIVO_REQUERIDO, ESTADO_NO_PERMITE_ANULAR, LIMITE_EXCEDIDO]'],
];

$copiar = static function (string $de, string $a): void {
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
/** @return array<string, array{0: string, 1: string}> métrica => [estado, valor] */
$medir = static function (string $copia): array {
    putenv('SQA_MICROHIS=' . $copia);
    putenv('PRESCRIPCIONES_DB_DSN');
    $salida = (string) shell_exec('php ' . escapeshellarg(__DIR__ . '/medir-sqa.php') . ' --sin-mutaciones 2>&1');
    preg_match_all('/^(VERDE|ÁMBAR|ROJO)\s+(M\d)\s.*\n\s+valor: (.*) · umbral:/mu', $salida, $m, PREG_SET_ORDER);
    $r = [];
    foreach ($m as $x) {
        $r[$x[2]] = [$x[1], trim($x[3])];
    }

    return $r;
};

$base = sys_get_temp_dir() . '/sqa-presc-base-' . bin2hex(random_bytes(3));
$copiar($micro, $base);
$referencia = $medir($base);
$borrar($base);
echo "Copia SIN defecto (referencia):\n";
foreach ($referencia as $id => [$estado, $valor]) {
    printf("   %-3s %-5s %s\n", $id, $estado, $valor);
}

$sinDetectar = 0;
foreach ($defectos as $nombre => [$archivo, $buscar, $reemplazo]) {
    $copia = sys_get_temp_dir() . '/sqa-presc-' . bin2hex(random_bytes(3));
    $copiar($micro, $copia);
    $codigo = (string) file_get_contents("$copia/$archivo");
    echo PHP_EOL . $nombre . PHP_EOL;
    if (substr_count($codigo, $buscar) !== 1) {
        echo "   NO SE PUDO INYECTAR\n";
        $sinDetectar++;
        $borrar($copia);
        continue;
    }
    file_put_contents("$copia/$archivo", str_replace($buscar, $reemplazo, $codigo));
    $medido = $medir($copia);
    $cambios = 0;
    foreach ($medido as $id => [$estado, $valor]) {
        if ($id !== 'M2' && $valor !== ($referencia[$id][1] ?? null)) {
            printf("   %-3s %-5s %s   (referencia: %s)\n", $id, $estado, $valor, $referencia[$id][1] ?? '?');
            $cambios++;
        }
    }
    if ($cambios === 0) {
        echo "   NO DETECTADO por ninguna métrica\n";
        $sinDetectar++;
    }
    $borrar($copia);
}
echo PHP_EOL . 'Defectos inyectados: ' . count($defectos) . '  Sin detectar: ' . $sinDetectar . PHP_EOL;
exit($sinDetectar === 0 ? 0 : 1);

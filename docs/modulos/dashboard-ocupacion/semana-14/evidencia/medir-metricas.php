<?php
declare(strict_types=1);

// Mide las métricas M-01 a M-05 y M-07 del plan SQA sobre el Micro-HIS del módulo y su historial en git.
// Uso: php medir-metricas.php (desde cualquier carpeta). Sale con 1 si alguna métrica no cumple su umbral.
$modulo = dirname(__DIR__, 2);
$raiz = glob("$modulo/micro-his-*", GLOB_ONLYDIR)[0] ?? null;
if ($raiz === null) { echo "El Micro-HIS del módulo no está en esta rama.\n"; exit(1); }
$pruebas = file_get_contents("$raiz/pruebas/pruebas.php");
$semana1 = file_get_contents(glob("$modulo/semana-01/semana-01-*.md")[0]);
$incumplidas = 0;

function metrica(string $id, string $nombre, string $valor, bool $cumple): void
{
    global $incumplidas; $incumplidas += $cumple ? 0 : 1;
    echo "$id  " . mb_str_pad($nombre, 48) . mb_str_pad($valor, 40) . ($cumple ? 'cumple' : 'NO CUMPLE') . "\n";
}
/** Porcentaje de identificadores de la semana 1 (R-xx o CU-xx) que alguna prueba cita por su nombre. */
function cobertura(string $prefijo, string $semana1, string $pruebas): array
{
    preg_match_all("/^\| ($prefijo-\d\d) \|/m", $semana1, $m);
    $ids = array_values(array_unique($m[1]));   // la matriz de trazabilidad repite los CU de la especificación
    $citados = array_filter($ids, fn (string $id) => str_contains($pruebas, $id));
    return [count($citados), count($ids), array_values(array_diff($ids, $citados))];
}
/** Líneas del método más largo de src/, contando desde «function» hasta su llave de cierre. */
function metodoMasLargo(string $carpeta): array
{
    $max = [0, ''];
    foreach (glob("$carpeta/src/*/*.php") as $archivo) {
        $t = token_get_all(file_get_contents($archivo));
        foreach ($t as $i => $tok) {
            if (!is_array($tok) || $tok[0] !== T_FUNCTION) continue;
            $nombre = ''; $nivel = 0; $inicio = $tok[2];
            for ($j = $i + 1; $j < count($t); $j++) {
                if ($nombre === '' && is_array($t[$j]) && $t[$j][0] === T_STRING) $nombre = $t[$j][1];
                if ($t[$j] === ';' && $nivel === 0) break;   // método de interfaz o abstracto: sin cuerpo
                if ($t[$j] === '{' || (is_array($t[$j]) && in_array($t[$j][0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) $nivel++;
                if ($t[$j] === '}' && --$nivel === 0) {
                    $fin = $inicio; for ($k = $j; $k > $i && !is_array($t[$k]); $k--); $fin = $t[$k][2] + substr_count($t[$k][1], "\n");
                    if ($fin - $inicio + 1 > $max[0]) $max = [$fin - $inicio + 1, basename($archivo, '.php') . "::$nombre"];
                    break;
                }
            }
        }
    }
    return $max;
}

// M-01: pruebas en verde.
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$raiz/pruebas/pruebas.php"), $salida, $codigo);
$ok = count(preg_grep('/^OK /', $salida)); $total = $ok + count(preg_grep('/^FALLA /', $salida));
metrica('M-01', 'Pruebas del Micro-HIS en verde', "$ok de $total", $codigo === 0 && $ok === $total);

// M-02 y M-03: trazabilidad de reglas y casos de uso de la semana 1 a las pruebas.
[$c, $n, $faltan] = cobertura('R', $semana1, $pruebas);
metrica('M-02', 'Reglas R-xx citadas por alguna prueba', sprintf('%d de %d (%d %%)', $c, $n, intdiv(100 * $c, $n)), 100 * $c >= 80 * $n);
if ($faltan) echo "      sin prueba: " . implode(', ', $faltan) . "\n";
[$c, $n, $faltan] = cobertura('CU', $semana1, $pruebas);
metrica('M-03', 'Casos de uso CU-xx citados por alguna prueba', sprintf('%d de %d (%d %%)', $c, $n, intdiv(100 * $c, $n)), 100 * $c >= 80 * $n);
if ($faltan) echo "      sin prueba: " . implode(', ', $faltan) . "\n";

// M-04: ninguna consulta concatena variables (inyección SQL).
$concatenadas = 0;
foreach (glob("$raiz/src/Persistencia/*.php") as $archivo) $concatenadas += preg_match_all('/(prepare|query|exec)\(\s*"[^"]*\$/', file_get_contents($archivo));
metrica('M-04', 'Consultas SQL que concatenan variables', (string) $concatenadas, $concatenadas === 0);

// M-05: mantenibilidad, el método más largo.
[$lineas, $donde] = metodoMasLargo($raiz);
metrica('M-05', 'Líneas del método más largo (máx. 15)', "$lineas ($donde)", $lineas <= 15);

// M-07: tamaño de cada PR del módulo integrado en develop (un commit por PR, sin PNG).
$repo = escapeshellarg(dirname($modulo, 3)); $ruta = escapeshellarg(substr($modulo, strlen(dirname($modulo, 3)) + 1));
exec("git -C $repo log --no-merges --format=%h origin/develop -- $ruta", $commits);
$tamanos = [];
foreach ($commits as $h) {
    exec("git -C $repo show --numstat --format= $h -- $ruta", $filas);
    $tamanos[$h] = array_sum(array_map(fn (string $f) => ($p = explode("\t", $f)) && $p[0] !== '-' ? (int) $p[0] + (int) $p[1] : 0, $filas));
    $filas = [];
}
metrica('M-07', 'Líneas del PR más grande integrado (máx. 400)', $tamanos ? max($tamanos) . ' (' . count($tamanos) . ' PR medidos)' : 'sin historial de git', $tamanos && max($tamanos) <= 400);

echo $incumplidas === 0 ? "RESULTADO: todas las métricas cumplen\n" : "RESULTADO: $incumplidas métrica(s) no cumplen su umbral\n";
exit($incumplidas === 0 ? 0 : 1);

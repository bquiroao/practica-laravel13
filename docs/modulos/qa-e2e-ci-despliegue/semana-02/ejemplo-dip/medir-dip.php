<?php

declare(strict_types=1);

/*
 * Semana 2 · Mide cuánto depende el nivel ALTO (la regla de calidad y el caso de uso) de los detalles, antes y después de DIP.
 * Cuenta, en el código de alto nivel:
 *   1. instancias de clases que no son del dominio (new GitHubActionsApi, new \PDO…);
 *   2. llamadas a E/S, red, entorno o reloj del sistema (getenv, file_get_contents, curl_*, time, date…);
 *   3. imports del espacio de nombres de adaptadores.
 *   php medir-dip.php      Código de salida: violaciones en el diseño DESPUÉS (debe ser 0).
 */

$dominio = ['Decision', 'ReglaRechazada', 'ResultadoEscenario', 'Ejecucion', 'PoliticaGate'];
$es = '/\b(getenv|file_get_contents|file_put_contents|fopen|simplexml_load_\w+|curl_\w+|time|date|strtotime|microtime|hash_file|is_file)\s*\(/';

$medir = static function (array $archivos) use ($dominio, $es): array {
    $r = ['instancias' => [], 'es' => [], 'imports' => []];
    foreach ($archivos as $archivo) {
        $codigo = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', (string) file_get_contents($archivo));   // sin comentarios
        preg_match_all('/\bnew\s+\\\\?([A-Za-z_][\w\\\\]*)/', $codigo, $m);
        foreach ($m[1] as $clase) {
            if (!in_array(basename(str_replace('\\', '/', $clase)), $dominio, true)) {
                $r['instancias'][] = $clase;
            }
        }
        preg_match_all($es, $codigo, $m);
        array_push($r['es'], ...$m[1]);
        preg_match_all('/^use\s+E2e\\\\Adaptadores\\\\(\w+)/m', $codigo, $m);
        array_push($r['imports'], ...$m[1]);
    }

    return $r;
};

$antes = $medir([__DIR__ . '/antes/EvaluadorGate.php']);
$despues = $medir([...glob(__DIR__ . '/despues/src/Dominio/*.php'), ...glob(__DIR__ . '/despues/src/Aplicacion/*.php')]);

printf("%-44s %8s %8s\n", 'Dependencia del nivel alto hacia un detalle', 'Antes', 'Después');
foreach (['instancias' => 'Instancias de clases concretas (new)', 'es' => 'E/S, red, entorno o reloj del sistema', 'imports' => 'Imports de adaptadores'] as $k => $t) {
    printf("%-44s %8d %8d\n", $t, count($antes[$k]), count($despues[$k]));
}
printf("\nAntes:   new %s\n         %s\n", implode(', new ', $antes['instancias']), implode(', ', array_map(static fn (string $f): string => "$f()", $antes['es'])));
$total = count($despues['instancias']) + count($despues['es']) + count($despues['imports']);
printf("Después: %s\n", $total === 0 ? 'ninguna (Dominio y Aplicación solo conocen las interfaces de Dominio/Puertos.php)' : json_encode($despues));
exit($total);

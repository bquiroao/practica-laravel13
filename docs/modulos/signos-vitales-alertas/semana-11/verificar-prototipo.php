<?php
declare(strict_types=1);

// Comprueba que el prototipo de la semana 11 dice lo mismo que el Micro-HIS:  php verificar-prototipo.php
// (limites, rangos, clasificación) y que cumple lo que el documento promete (pantallas, accesibilidad, móvil, capturas).
use SignosVitales\Dominio\{EvaluadorUmbrales, Medicion, RangoSigno, ValidadorLimitesFisicos};

$raiz = dirname(__DIR__);
require "$raiz/micro-his-signos-vitales/bootstrap.php";
$html = (string) file_get_contents(__DIR__ . '/prototipo/index.html');
$doc = (string) file_get_contents(__DIR__ . '/semana-11-prototipo-navegable.md');
$fallas = 0;
function ver(string $que, bool $ok, string $detalle = ''): void { global $fallas; $fallas += $ok ? 0 : 1; printf("%-6s %s%s\n", $ok ? 'PASA' : 'FALLA', $que, $ok || $detalle === '' ? '' : " → $detalle"); }

preg_match('#<script id="reglas" type="application/json">(.*?)</script>#s', $html, $m);
$reglas = json_decode($m[1] ?? 'null', true) ?? [];
ver('P-01 límites físicos del prototipo = ValidadorLimitesFisicos::LIMITES', ($reglas['limites'] ?? null) == ValidadorLimitesFisicos::LIMITES);

preg_match("/INSERT INTO rangos_signos_vitales VALUES (.*?);/s", (string) file_get_contents("$raiz/micro-his-signos-vitales/sql/esquema.sql"), $s);
preg_match_all("/\('(\w+)', 18, 120, ([\d.]+), ([\d.]+), ([\d.]+), ([\d.]+)\)/", $s[1] ?? '', $f, PREG_SET_ORDER);
$sql = []; foreach ($f as $x) { $sql[$x[1]] = [(float) $x[2], (float) $x[3], (float) $x[4], (float) $x[5]]; }
ver('P-02 rangos de adulto del prototipo = sql/esquema.sql', count($sql) === 6 && ($reglas['rangos_adulto'] ?? null) == $sql);

// P-03: la función clasificar() del prototipo (JavaScript) y EvaluadorUmbrales (PHP) dan lo mismo en 6 signos × 40 valores.
$node = trim((string) shell_exec(PHP_OS_FAMILY === 'Windows' ? 'where node 2>NUL' : 'command -v node'));
if ($node === '') { echo "OMITIDO P-03 (no hay node; se corre aparte con node)\n"; }
else {
    preg_match('#<script id="logica">(.*?)</script>#s', $html, $l);
    $js = 'const document={getElementById:()=>({textContent:' . json_encode($m[1]) . '})};' . $l[1]
        . 'const o={};for(const t of Object.keys(REGLAS.limites)){const [a,b]=REGLAS.limites[t];o[t]=[];for(let i=0;i<=40;i++){const v=+(a+(b-a)*i/40).toFixed(1);o[t].push([v,clasificar(t,v,40)]);}}'
        . 'o.edad=[clasificar("fc",70,5),clasificar("fc",70,17),clasificar("fc",70,18),clasificar("fc",70,120),clasificar("fc",70,121)];console.log(JSON.stringify(o));';
    $archivo = tempnam(sys_get_temp_dir(), 'sv11'); file_put_contents($archivo, $js);
    $js_sal = json_decode((string) shell_exec('node ' . escapeshellarg($archivo)), true) ?? []; unlink($archivo);
    $dif = [];
    foreach (ValidadorLimitesFisicos::LIMITES as $t => $_) {
        $r = $sql[$t];
        foreach ($js_sal[$t] ?? [] as [$v, $nivel]) {
            $php = EvaluadorUmbrales::estandar()->evaluar([new Medicion(\SignosVitales\Dominio\TipoSigno::from($t), (float) $v)], [$t => new RangoSigno($r[0], $r[1], $r[2], $r[3])])[$t]->value;
            if ($php !== $nivel) { $dif[] = "$t=$v js=$nivel php=$php"; }
        }
    }
    ver('P-03 clasificar() del prototipo = EvaluadorUmbrales (246 valores)', $dif === [] && count($js_sal) === 7, implode('; ', array_slice($dif, 0, 3)));
    ver('P-04 fuera de 18–120 años el prototipo dice sin_rango (igual que el Micro-HIS)', ($js_sal['edad'] ?? []) === ['sin_rango', 'sin_rango', 'normal', 'normal', 'sin_rango']);
}

preg_match_all('/data-pantalla="(\w+)"/', $html, $a); preg_match_all('/`pantalla:(\w+)`/', $doc, $b);
ver('P-05 las pantallas del prototipo son las del mapa de navegación', array_unique($a[1]) !== [] && array_unique($a[1]) == array_unique($b[1]), implode(',', $a[1]) . ' / ' . implode(',', $b[1]));
ver('P-06 accesibilidad: lang, viewport, label por campo, aria-live y role=alert', str_contains($html, '<html lang="es">') && str_contains($html, 'name="viewport"')
    && str_contains($html, "<label for=\"'+t+'\"") && str_contains($html, 'aria-live="polite"') && str_contains($html, 'role="alert"'));
ver('P-07 móvil: media query ≤ 430 px y botones de 44 px', (bool) preg_match('/max-width:\s*430px/', $html) && substr_count($html, 'min-height:44px') >= 2);
$png = glob(__DIR__ . '/evidencia/*.png'); $validos = array_filter($png, fn ($p) => str_starts_with((string) file_get_contents($p, false, null, 0, 8), "\x89PNG") && filesize($p) > 5000);
ver('P-08 cuatro capturas PNG (escritorio y móvil × camino feliz y error crítico)', count($validos) === 4 && count(array_filter($png, fn ($p) => preg_match('/(escritorio|movil)-(camino-feliz|error-critico)\.png$/', $p))) === 4);
ver('P-09 el documento enlaza cada captura', count(array_filter($png, fn ($p) => str_contains($doc, 'evidencia/' . basename($p)))) === 4);

echo $fallas === 0 ? "Resultado: el prototipo coincide con el Micro-HIS y con el documento\n" : "Resultado: $fallas FALLA(S)\n";
exit($fallas === 0 ? 0 : 1);

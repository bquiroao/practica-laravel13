<?php
declare(strict_types=1);

// Comprueba que el prototipo de la semana 11 dice lo mismo que el Micro-HIS:  php verificar-prototipo.php
// (catálogo, datos, CSV con su huella, validación de filtros) y que cumple lo que promete el documento (pantallas, accesibilidad, móvil, capturas).
use Reportes\Aplicacion\{ExportarReporte, Usuario};
use Reportes\Dominio\{Csv, ErrorDominio, Filtros};
use Reportes\Persistencia\ReportesPdo;
use function Reportes\Dominio\catalogo;

$raiz = dirname(__DIR__);
require "$raiz/micro-his-reportes/bootstrap.php";
$html = (string) file_get_contents(__DIR__ . '/prototipo/index.html');
$doc = (string) file_get_contents(__DIR__ . '/semana-11-prototipo-navegable.md');
$fallas = 0;
function ver(string $que, bool $ok, string $detalle = ''): void { global $fallas; $fallas += $ok ? 0 : 1; printf("%-6s %s%s\n", $ok ? 'PASA' : 'FALLA', $que, $ok || $detalle === '' ? '' : " → $detalle"); }

preg_match('#<script id="reglas" type="application/json">(.*?)</script>#s', $html, $m);
$reglas = json_decode($m[1] ?? 'null', true) ?? [];
$cat = catalogo();
$igualCatalogo = count($reglas['catalogo'] ?? []) === count($cat);
foreach ($cat as $codigo => $d) { $p = $reglas['catalogo'][$codigo] ?? []; $igualCatalogo = $igualCatalogo && ($p['columnas'] ?? null) === $d->columnas && ($p['filtro'] ?? null) === [array_key_first($d->filtros), array_values($d->filtros)[0]]; }
ver('P-01 catálogo del prototipo (columnas, datos personales y filtro) = catalogo() del Micro-HIS', $igualCatalogo);

$pdo = new PDO('sqlite::memory:'); $pdo->exec((string) file_get_contents("$raiz/micro-his-reportes/sql/esquema.sql"));
$delEsquema = ['censo' => array_map('array_values', $pdo->query('SELECT * FROM vw_censo_servicio')->fetchAll(PDO::FETCH_ASSOC)), 'egresos' => array_map('array_values', $pdo->query('SELECT * FROM vw_egresos')->fetchAll(PDO::FETCH_ASSOC))];
$faltan = 0; foreach ($delEsquema as $k => $filas) { foreach ($filas as $f) { $faltan += in_array($f, $reglas['datos'][$k] ?? [], true) ? 0 : 1; } }
ver('P-02 todas las filas de sql/esquema.sql están en los datos del prototipo (más una fila tardía)', $faltan === 0 && count($reglas['datos']['censo'] ?? []) === count($delEsquema['censo']) + 1);

$node = trim((string) shell_exec(PHP_OS_FAMILY === 'Windows' ? 'where node 2>NUL' : 'command -v node'));
if ($node === '') { echo "OMITIDO P-03 y P-04 (no hay node; se corren aparte con node)\n"; }
else {
    preg_match('#<script id="logica">(.*?)</script>#s', $html, $l);
    $js = 'const document={getElementById:()=>({textContent:' . json_encode($m[1]) . '})};' . $l[1] . 'const crypto=require("crypto");const o={csv:{},val:[]};'
        . 'const esc=[["censo",{desde:"2026-10-01",hasta:"2026-10-25",extra:""},true],["censo",{desde:"2026-10-01",hasta:"2026-10-25",extra:""},false],["egresos",{desde:"2026-10-01",hasta:"2026-10-25",extra:""},true],["censo",{desde:"2026-10-01",hasta:"2026-10-25",extra:"cirugia"},true]];'
        . 'esc.forEach(([c,f,v],i)=>{const n=REGLAS.catalogo[c].filtro[0];const t=generarCsv(c,filtrar(c,f,n),v);o.csv[i]=[t,crypto.createHash("sha256").update(t).digest("hex")];});'
        . 'const casos=[["2026-10-01","2026-10-25",""],["2026-10-25","2026-10-01",""],["2026-01-01","2026-10-25",""],["2026-07-25","2026-10-25",""],["2026-07-24","2026-10-25",""],["2026-10-01","2026-10-26",""],["2026-10-01","2026-10-25","urgencias"],["2026/10/01","2026-10-25",""],["","2026-10-25",""],["2026-02-31","2026-03-02",""],["2026-13-45","2026-13-46",""]];'
        . 'casos.forEach(([d,h,x])=>{const r=validar({desde:d,hasta:h,extra:x},"servicio",REGLAS.catalogo.censo.filtro[1]);o.val.push(r?r.campo:null);});console.log(JSON.stringify(o));';
    $archivo = tempnam(sys_get_temp_dir(), 'rp11'); file_put_contents($archivo, $js);
    $js_sal = json_decode((string) shell_exec('node ' . escapeshellarg($archivo)), true) ?? []; unlink($archivo);

    // Mismo origen de datos que el prototipo, en SQLite, y el caso de uso real del Micro-HIS con el mismo reloj.
    $pdo = new PDO('sqlite::memory:'); $pdo->exec((string) file_get_contents("$raiz/micro-his-reportes/sql/esquema.sql")); $pdo->exec('DELETE FROM vw_censo_servicio; DELETE FROM vw_egresos');
    foreach ($reglas['datos']['censo'] as $f) { $pdo->prepare('INSERT INTO vw_censo_servicio VALUES (?,?,?,?,?,?,?)')->execute($f); }
    foreach ($reglas['datos']['egresos'] as $f) { $pdo->prepare('INSERT INTO vw_egresos VALUES (?,?,?,?,?,?)')->execute($f); }
    $repo = new ReportesPdo($pdo); $caso = new ExportarReporte($repo, $repo, $cat, ['csv' => new Csv()], fn () => $reglas['corte']);
    $escenarios = [['censo', [], true], ['censo', [], false], ['egresos', [], true], ['censo', ['servicio' => 'cirugia'], true]];
    $dif = [];
    foreach ($escenarios as $i => [$codigo, $extra, $personales]) {
        $r = $caso->ejecutar($codigo, ['desde' => '2026-10-01', 'hasta' => '2026-10-25'] + $extra, 'csv', new Usuario('u', 'jefatura', 'hosp-1', $personales));
        if (($js_sal['csv'][$i] ?? null) !== [$r['contenido'], $r['hash']]) { $dif[] = "escenario $i"; }
    }
    ver('P-03 el CSV y su huella SHA-256 del prototipo = ExportarReporte (4 escenarios, con y sin datos personales)', $dif === [] && count($js_sal['csv'] ?? []) === 4, implode(', ', $dif));

    $hoy = substr($reglas['corte'], 0, 10); $dif = []; $val = $js_sal['val'] ?? []; $conocidas = [];
    foreach ([['2026-10-01', '2026-10-25', ''], ['2026-10-25', '2026-10-01', ''], ['2026-01-01', '2026-10-25', ''], ['2026-07-25', '2026-10-25', ''], ['2026-07-24', '2026-10-25', ''], ['2026-10-01', '2026-10-26', ''],
              ['2026-10-01', '2026-10-25', 'urgencias'], ['2026/10/01', '2026-10-25', ''], ['', '2026-10-25', ''], ['2026-02-31', '2026-03-02', ''], ['2026-13-45', '2026-13-46', '']] as $i => [$d, $h, $x]) {
        try { Filtros::desde(['desde' => $d, 'hasta' => $h] + ($x === '' ? [] : ['servicio' => $x]), $cat['censo'], $hoy); $php = null; }
        catch (ErrorDominio $e) { $php = $e->campo; } catch (\Throwable) { $php = 'EXCEPCIÓN'; }
        if ($i >= 9) { $conocidas[] = "«{$d}» → prototipo " . json_encode($val[$i] ?? '?') . ', Micro-HIS ' . json_encode($php); continue; }
        if (!array_key_exists($i, $val) || $val[$i] !== $php) { $dif[] = "caso $i"; }
    }
    ver('P-04 la validación de filtros del prototipo = Filtros::desde en 9 casos (incluye 92 y 93 días)', $dif === [] && count($val) === 11, implode(', ', $dif));
    foreach ($conocidas as $c) { echo "INFO   diferencia conocida (el prototipo ya rechaza el calendario inválido; el Micro-HIS aún no): $c\n"; }
}

preg_match_all('/data-pantalla="(\w+)"/', $html, $a); preg_match_all('/`pantalla:(\w+)`/', $doc, $b);
ver('P-05 las pantallas del prototipo son las del mapa de navegación', array_unique($a[1]) !== [] && array_unique($a[1]) == array_unique($b[1]), implode(',', $a[1]) . ' / ' . implode(',', $b[1]));
ver('P-06 accesibilidad: lang, viewport, label por campo, aria-live y role=alert', str_contains($html, '<html lang="es">') && str_contains($html, 'name="viewport"') && preg_match_all('/<label for="/', $html) >= 4
    && str_contains($html, 'aria-live="polite"') && str_contains($html, 'role="alert"'));
ver('P-07 móvil: media query ≤ 430 px y botones de 44 px', (bool) preg_match('/max-width:\s*430px/', $html) && substr_count($html, 'min-height:44px') >= 2);
$png = glob(__DIR__ . '/evidencia/*.png'); $validos = array_filter($png, fn ($p) => str_starts_with((string) file_get_contents($p, false, null, 0, 8), "\x89PNG") && filesize($p) > 5000);
ver('P-08 cuatro capturas PNG (escritorio y móvil × camino feliz y error crítico)', count($validos) === 4 && count(array_filter($png, fn ($p) => preg_match('/(escritorio|movil)-(camino-feliz|error-critico)\.png$/', $p))) === 4);
ver('P-09 el documento enlaza cada captura', count(array_filter($png, fn ($p) => str_contains($doc, 'evidencia/' . basename($p)))) === 4);

echo $fallas === 0 ? "Resultado: el prototipo coincide con el Micro-HIS y con el documento\n" : "Resultado: $fallas FALLA(S)\n";
exit($fallas === 0 ? 0 : 1);

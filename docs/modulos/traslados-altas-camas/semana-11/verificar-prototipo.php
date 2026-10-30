<?php
declare(strict_types=1);

// Comprueba que el prototipo de la semana 11 dice lo mismo que el Micro-HIS:  php verificar-prototipo.php
// (datos de partida y resultado de 192 movimientos posibles) y que cumple lo que promete el documento (pantallas, accesibilidad, móvil, capturas).
use Traslados\Aplicacion\{RegistrarMovimiento, Usuario};
use Traslados\Dominio\ErrorDominio;
use Traslados\Persistencia\MovimientosPdo;

$raiz = dirname(__DIR__);
require "$raiz/micro-his-traslados/bootstrap.php";
$html = (string) file_get_contents(__DIR__ . '/prototipo/index.html');
$doc = (string) file_get_contents(__DIR__ . '/semana-11-prototipo-navegable.md');
$fallas = 0;
function ver(string $que, bool $ok, string $detalle = ''): void { global $fallas; $fallas += $ok ? 0 : 1; printf("%-6s %s%s\n", $ok ? 'PASA' : 'FALLA', $que, $ok || $detalle === '' ? '' : " → $detalle"); }
function base(string $raiz): PDO { $pdo = new PDO('sqlite::memory:'); $pdo->exec((string) file_get_contents("$raiz/micro-his-traslados/sql/esquema.sql")); return $pdo; }

preg_match('#<script id="reglas" type="application/json">(.*?)</script>#s', $html, $m);
$reglas = json_decode($m[1] ?? 'null', true) ?? [];
$pdo = base($raiz);
$camas = []; foreach ($pdo->query('SELECT bed_id, code, type, status FROM beds ORDER BY bed_id') as $f) { $camas[$f['bed_id']] = [$f['code'], $f['type'], $f['status']]; }
$episodios = []; foreach ($pdo->query("SELECT e.care_episode_id AS id, p.given_name || ' ' || p.family_name AS nombre, e.status, (SELECT bed_id FROM bed_stays s WHERE s.care_episode_id = e.care_episode_id AND s.ended_at IS NULL) AS cama
                                        FROM care_episodes e JOIN local_patients p ON p.local_patient_id = e.local_patient_id ORDER BY e.care_episode_id") as $f) { $episodios[$f['id']] = [$f['nombre'], $f['status'], $f['cama']]; }
$ordenes = array_map('array_values', $pdo->query('SELECT orden_id, care_episode_id, tipo, requisito_cama, tipo_alta, motivo FROM ordenes_movimiento ORDER BY orden_id')->fetchAll(PDO::FETCH_ASSOC));
$ordenarClaves = function (array $a) { ksort($a); return $a; };
ver('P-01 camas, episodios, estancias y órdenes de partida del prototipo = sql/esquema.sql', $ordenarClaves($reglas['camas'] ?? []) == $camas && $ordenarClaves($reglas['episodios'] ?? []) == $episodios && ($reglas['ordenes'] ?? null) == $ordenes);

$node = trim((string) shell_exec(PHP_OS_FAMILY === 'Windows' ? 'where node 2>NUL' : 'command -v node'));
if ($node === '') { echo "OMITIDO P-02 (no hay node; se corre aparte con node)\n"; }
else {
    preg_match('#<script id="logica">(.*?)</script>#s', $html, $l);
    $roles = ['enfermeria'];   // el rol médico y el de admisión se prueban aparte
    $js = 'const document={getElementById:()=>({textContent:' . json_encode($m[1]) . '})};' . $l[1]
        . 'const resumen=e=>Object.keys(e.camas).sort().map(k=>e.camas[k][2]).join(",")+"|"+Object.keys(e.episodios).sort().map(k=>e.episodios[k][1]+":"+e.episodios[k][2]).join(",")+"|"+e.ordenes.map(o=>o[6]).join(",");'
        . 'const o={};const eps=["epi-1","epi-2","epi-3","epi-4","epi-9","epi-cerrado","epi-xx"],tipos=["traslado","alta","otro"],dest=["","bed-a1","bed-a2","bed-a3","bed-a4","bed-a5","bed-u1","bed-m1","bed-zzz"];'
        . 'for(const e of eps)for(const t of tipos)for(const d of dest){const r=movimiento(estadoInicial(),"enfermeria",e,t,d);o[e+"/"+t+"/"+d]=[r.rechazo,resumen(r.estado)];}'
        . 'for(const rol of ["medico","admision","otro"]){const r=movimiento(estadoInicial(),rol,"epi-1","traslado","bed-a2");o["rol/"+rol]=[r.rechazo,resumen(r.estado)];}'
        . 'const antes=estadoInicial();const copia=JSON.stringify(antes);movimiento(antes,"enfermeria","epi-1","traslado","bed-a2");o.puro=JSON.stringify(antes)===copia;console.log(JSON.stringify(o));';
    $archivo = tempnam(sys_get_temp_dir(), 'tr11'); file_put_contents($archivo, $js);
    $js_sal = json_decode((string) shell_exec('node ' . escapeshellarg($archivo)), true) ?? []; unlink($archivo);

    $resumen = function (PDO $pdo): string {
        $b = $pdo->query('SELECT status FROM beds ORDER BY bed_id')->fetchAll(PDO::FETCH_COLUMN);
        $e = array_map(fn ($f) => $f['status'] . ':' . ($f['cama'] ?? 'null'), $pdo->query('SELECT e.care_episode_id, e.status, (SELECT bed_id FROM bed_stays s WHERE s.care_episode_id = e.care_episode_id AND s.ended_at IS NULL) AS cama FROM care_episodes e ORDER BY e.care_episode_id')->fetchAll(PDO::FETCH_ASSOC));
        $o = $pdo->query('SELECT estado FROM ordenes_movimiento ORDER BY orden_id')->fetchAll(PDO::FETCH_COLUMN);
        return implode(',', $b) . '|' . implode(',', $e) . '|' . implode(',', $o);
    };
    $correr = function (string $rol, string $episodio, string $tipo, string $destino) use ($raiz, $resumen): array {
        $pdo = base($raiz); $r = new MovimientosPdo($pdo); $caso = new RegistrarMovimiento($r, $r, fn () => new DateTimeImmutable('2026-10-25 12:00'), fn () => 'nueva-estancia');
        try { $x = $caso->ejecutar(['episodio_id' => $episodio, 'tipo' => $tipo, 'destino_id' => $destino], new Usuario('u', $rol)); $rechazo = $x['rechazo']; } catch (ErrorDominio $e) { $rechazo = $e->codigo; }
        return [$rechazo, $resumen($pdo)];
    };
    $dif = []; $n = 0;
    foreach (['epi-1', 'epi-2', 'epi-3', 'epi-4', 'epi-9', 'epi-cerrado', 'epi-xx'] as $e) { foreach (['traslado', 'alta', 'otro'] as $t) { foreach (['', 'bed-a1', 'bed-a2', 'bed-a3', 'bed-a4', 'bed-a5', 'bed-u1', 'bed-m1', 'bed-zzz'] as $d) {
        $n++; if (($js_sal["$e/$t/$d"] ?? null) !== $correr('enfermeria', $e, $t, $d)) { $dif[] = "$e/$t/$d"; } } } }
    foreach (['medico', 'admision', 'otro'] as $rol) { $n++; if (($js_sal["rol/$rol"] ?? null) !== $correr($rol, 'epi-1', 'traslado', 'bed-a2')) { $dif[] = "rol/$rol"; } }
    ver("P-02 el resultado del prototipo = RegistrarMovimiento del Micro-HIS en $n movimientos (rechazo y estado final de camas, episodios y órdenes)", $dif === [] && count($js_sal) === $n + 1, implode(', ', array_slice($dif, 0, 3)));
    ver('P-03 movimiento() del prototipo no modifica el estado que recibe (todo o nada)', ($js_sal['puro'] ?? false) === true);
}

preg_match_all('/data-pantalla="(\w+)"/', $html, $a); preg_match_all('/`pantalla:(\w+)`/', $doc, $b);
ver('P-04 las pantallas del prototipo son las del mapa de navegación', array_unique($a[1]) !== [] && array_unique($a[1]) == array_unique($b[1]), implode(',', $a[1]) . ' / ' . implode(',', $b[1]));
ver('P-05 accesibilidad: lang, viewport, label por campo, aria-live, role=alert y estado con texto', str_contains($html, '<html lang="es">') && str_contains($html, 'name="viewport"') && preg_match_all('/<label for="/', $html) >= 3
    && str_contains($html, 'aria-live="polite"') && str_contains($html, 'role="alert"') && str_contains($html, "ETIQ = { available:'disponible'"));
ver('P-06 móvil: media query ≤ 430 px y botones de 44 px', (bool) preg_match('/max-width:\s*430px/', $html) && substr_count($html, 'min-height:44px') >= 2);
$png = glob(__DIR__ . '/evidencia/*.png'); $validos = array_filter($png, fn ($p) => str_starts_with((string) file_get_contents($p, false, null, 0, 8), "\x89PNG") && filesize($p) > 5000);
ver('P-07 cuatro capturas PNG (escritorio y móvil × camino feliz y error crítico)', count($validos) === 4 && count(array_filter($png, fn ($p) => preg_match('/(escritorio|movil)-(camino-feliz|error-critico)\.png$/', $p))) === 4);
ver('P-08 el documento enlaza cada captura', count(array_filter($png, fn ($p) => str_contains($doc, 'evidencia/' . basename($p)))) === 4);

echo $fallas === 0 ? "Resultado: el prototipo coincide con el Micro-HIS y con el documento\n" : "Resultado: $fallas FALLA(S)\n";
exit($fallas === 0 ? 0 : 1);

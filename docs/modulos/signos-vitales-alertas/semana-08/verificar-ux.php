<?php
declare(strict_types=1);

// Semana 8 — Comprueba que el diseño de UX coincide con el código del Micro-HIS (sin ejecutarlo) y que los entregables existen.
// Uso desde la raíz del repo: php docs/modulos/signos-vitales-alertas/semana-08/verificar-ux.php
$micro = dirname(__DIR__) . '/micro-his-signos-vitales/src'; $fallas = 0;
function ver(string $id, bool $ok, string $que): void { $GLOBALS['fallas'] += $ok ? 0 : 1; printf("%-6s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $que); }
$leer = fn (string $r) => (string) @file_get_contents($r);
$doc = $leer(__DIR__ . '/semana-08-diseno-ux.md'); $html = $leer(__DIR__ . '/wireframes/wireframes.html');
$codigo = implode("\n", array_map($leer, glob("$micro/*/*.php")));
$tabla = fn (string $re) => (preg_match_all($re, $doc, $m, PREG_SET_ORDER) ? $m : []);

// Códigos de error: los que puede emitir el código = los que tienen mensaje en el diseño
preg_match_all("/'(\w+)' => \d{3}|new ErrorDominio\('(\w+)'|VistaJson::error\('(\w+)'/", $codigo, $m);
$emitidos = array_values(array_unique(array_filter(array_merge($m[1], $m[2], $m[3]))));
$conMensaje = array_column($tabla('/^\| `(\w+)` \| (\d{3}) \| (.{10,}?) \| (.+?) \|$/m'), 1);
sort($emitidos); sort($conMensaje);
ver('UX-01', $emitidos !== [] && $emitidos === $conMensaje, sprintf('Los %d códigos de error del servidor tienen mensaje y acción en el diseño, y el diseño no inventa otros (distintos: %s)', count($emitidos), implode(', ', array_merge(array_diff($emitidos, $conMensaje), array_diff($conMensaje, $emitidos))) ?: 'ninguno'));

// Límites físicos
preg_match_all("/'(pas|pad|fc|fr|temp|spo2)' => \[(\d+), (\d+)\]/", $codigo, $m, PREG_SET_ORDER);
$limites = array_column(array_map(fn ($x) => [$x[1], "{$x[2]} a {$x[3]}"], $m), 1, 0);
$campos = $tabla('/^\| (PAS|PAD|FC|FR|Temp|SpO2) \| [^|]+ \| (\S+) \| (\d+ a \d+) \|$/m');
$delDoc = array_column(array_map(fn ($x) => [strtolower($x[1]), $x[3]], $campos), 1, 0);
ver('UX-02', count($limites) === 6 && $limites === $delDoc, 'Los 6 rangos posibles del formulario son los límites físicos del servidor (ValidadorLimitesFisicos)');
$sinUnidad = array_filter(array_column($campos, 2), fn ($u) => !str_contains($html, "<td>$u</td>"));
ver('UX-03', count($campos) === 6 && $sinUnidad === [], 'Cada signo tiene su unidad en el diseño y en el wireframe W2' . ($sinUnidad ? ' (falta: ' . implode(', ', $sinUnidad) . ')' : ''));

// Niveles
preg_match('/enum Clasificacion: string \{(.*?)\}/s', $codigo, $e); preg_match_all("/= '(\w+)'/", $e[1] ?? '', $n);
$niveles = array_column($tabla('/^\| (normal|anormal|critica|sin_rango) \| (\S+) \| (\S.*?) \| .+ \|$/m'), 1);
sort($n[1]); sort($niveles);
ver('UX-04', $n[1] !== [] && $n[1] === $niveles && str_contains($html, 'CRÍTICA') && str_contains($html, 'ANORMAL'), 'Los 4 niveles de clasificación tienen símbolo y texto en el diseño, y los wireframes los muestran con texto');

// Roles
$ok = str_contains($leer("$micro/Aplicacion/RegistrarToma.php"), "rol !== 'enfermeria'") && str_contains($leer("$micro/Aplicacion/ConsultarTomas.php"), "['enfermeria', 'medico']")
    && str_contains($doc, '| Registrar toma | Sí | No |') && str_contains($doc, '| Consultar tomas y alertas | Sí | Sí |');
ver('UX-05', $ok, 'La matriz de roles del diseño (registra solo enfermería; consultan enfermería y médico) coincide con los casos de uso');

// Entregables
$imagenes = array_merge(['diagramas/semana-08-flujo-por-rol.png'], array_map(fn ($i) => "wireframes/semana-08-wf$i.png", range(1, 6)));
$faltan = array_filter($imagenes, fn ($r) => !is_file(__DIR__ . "/$r") || !str_contains($doc, "]($r)"));
ver('UX-06', $faltan === [] && preg_match_all('/id="wf[1-6]"/', $html) === 6, 'El flujo y los 6 wireframes existen y están enlazados en el documento' . ($faltan ? ' (falta: ' . implode(', ', $faltan) . ')' : ''));
echo $fallas === 0 ? "\nResultado: el diseño coincide con el código y los entregables están completos.\n" : "\nResultado: $fallas verificaciones fallaron.\n";
exit($fallas ? 1 : 0);

<?php
declare(strict_types=1);

// Semana 7 — Demuestra el refactor con el código real del Micro-HIS (semana 4) y el ejemplo «después».
//   CAR: dónde vive el camino filtros → filas → archivo.  EQV/ERR/ATO: mismo resultado, mismos errores, mismo todo-o-nada.  CAM: una regla nueva en un solo lugar.  CON/DOC: contratos y entregables.
// Uso desde la raíz del repo: php docs/modulos/reportes-operativos-exportables/semana-07/verificar-refactor.php
use Reportes\Aplicacion\{ErrorPersistencia, ExportarReporte, Usuario};
use Reportes\Dominio\{Csv, ErrorDominio};
use Reportes\Persistencia\EnMemoria;
use Reportes\Refactor\{ExportarReporteDespues, PreparadorDeArchivo};
use function Reportes\Dominio\catalogo;

$micro = dirname(__DIR__) . '/micro-his-reportes'; $fallas = 0;
function ver(string $id, bool $ok, string $que): void { $GLOBALS['fallas'] += $ok ? 0 : 1; printf("%-7s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $que); }
require "$micro/bootstrap.php"; require __DIR__ . '/ejemplo-refactor/despues/ExportarReporte.php';

// Dónde vive el camino
$ruta = fn (string $c) => (string) file_get_contents($c);
$piezas = ['Filtros::desde(', 'fuente->filas(', 'Archivo::generar('];
$cuenta = fn (string $t) => array_map(fn ($p) => substr_count($t, $p), $piezas);
$antes = $cuenta($ruta("$micro/src/Aplicacion/ExportarReporte.php")); $exp = $ruta(__DIR__ . '/ejemplo-refactor/despues/ExportarReporte.php');
[$ini, $fin] = [strpos($exp, 'final class ExportarReporteDespues'), 0]; $despuesCaso = $cuenta(substr($exp, $ini)); $despuesPrep = $cuenta(substr($exp, 0, $ini));
ver('CAR-01', $antes === [2, 2, 2], sprintf('ANTES: ExportarReporte repite el camino en ejecutar y en reproducir (desde, filas, generar = %s)', implode('/', $antes)));
ver('CAR-02', $despuesPrep === [1, 1, 1] && $despuesCaso === [0, 0, 0], sprintf('DESPUÉS: el camino está una sola vez en PreparadorDeArchivo (%s) y 0 veces en el caso de uso (%s)', implode('/', $despuesPrep), implode('/', $despuesCaso)));

// Dos «mundos» iguales: el código de la semana 4 y el nuevo, cada uno con su repositorio en memoria
$reloj = fn () => '2026-10-25 12:00:00';
$mundo = function (?array $vistas = null) use ($reloj): array {
    $a = $vistas ? new EnMemoria($vistas) : EnMemoria::demo(); $b = $vistas ? new EnMemoria($vistas) : EnMemoria::demo();
    return [$a, new ExportarReporte($a, $a, catalogo(), ['csv' => new Csv()], $reloj), $b, new ExportarReporteDespues($b, new PreparadorDeArchivo($b, catalogo(), ['csv' => new Csv()]), $reloj), $b];
};
$jefa = new Usuario('usr-jefa', 'jefatura', 'hosp-1', true); $auditor = new Usuario('usr-aud', 'auditor', 'hosp-1', false); $enf = new Usuario('usr-enf', 'enfermeria', 'hosp-1', false);
$rango = ['desde' => '2026-10-01', 'hasta' => '2026-10-25'];
$sinId = fn (array $r) => array_diff_key($r, ['id' => 1]);

foreach ([['EQV-01', 'censo con datos personales (jefatura)', 'censo', $rango + ['servicio' => ' Medicina '], $jefa], ['EQV-02', 'egresos enmascarado (auditor)', 'egresos', $rango, $auditor]] as [$id, $que, $codigo, $f, $u]) {
    [$ra, $a, $rb, $b] = $mundo(); $x = $a->ejecutar($codigo, $f, 'csv', $u); $y = $b->ejecutar($codigo, $f, 'csv', $u);
    $iguales = $sinId($x) === $sinId($y) && $ra->exportacion($x['id'], 'hosp-1') === $rb->exportacion($y['id'], 'hosp-1') && $a->reproducir($x['id'], $u) === ['id' => $x['id'], 'coincide' => true, 'hash' => $x['hash']] && $b->reproducir($y['id'], $u) === ['id' => $y['id'], 'coincide' => true, 'hash' => $y['hash']];
    ver($id, $iguales, "Exportar $que: archivo, hash, filas y registro guardado son idénticos, y reproducir coincide en los dos");
}

// Reproducibilidad: una fila registrada después del corte no entra, ni al exportar ni al reproducir
$col = ['hospital_id', 'paciente', 'documento', 'servicio', 'cama', 'fecha', 'registrado_en'];
$vistas = ['vw_censo_servicio' => array_map(fn ($f) => array_combine($col, $f), [['hosp-1', 'Ana López', '1111', 'medicina', 'A1', '2026-10-20', '2026-10-20 08:00:00'], ['hosp-1', 'Tarde Tarde', '5555', 'medicina', 'A9', '2026-10-22', '2026-10-25 12:00:01']]), 'vw_egresos' => []];
[$ra, $a, $rb, $b] = $mundo($vistas); $x = $a->ejecutar('censo', $rango, 'csv', $jefa); $y = $b->ejecutar('censo', $rango, 'csv', $jefa);
ver('EQV-03', $x['filas'] === 1 && $y['filas'] === 1 && $x['hash'] === $y['hash'] && $a->reproducir($x['id'], $jefa)['coincide'] && $b->reproducir($y['id'], $jefa)['coincide'], 'La fila registrada un segundo después del corte queda fuera en los dos, y reproducir sigue coincidiendo');

// Mismos errores, con el mismo código y campo
$err = function (callable $f): string { try { $f(); return 'sin error'; } catch (ErrorDominio $e) { return $e->codigo . ':' . ($e->campo ?? ''); } catch (ErrorPersistencia) { return 'error_persistencia'; } };
$casos = [['rol', fn ($c) => $c->ejecutar('censo', $rango, 'csv', $enf)], ['reporte', fn ($c) => $c->ejecutar('nada', $rango, 'csv', $jefa)], ['formato', fn ($c) => $c->ejecutar('censo', $rango, 'pdf', $jefa)],
          ['sin desde', fn ($c) => $c->ejecutar('censo', ['hasta' => '2026-10-25'], 'csv', $jefa)], ['filtro ajeno', fn ($c) => $c->ejecutar('censo', $rango + ['servicio' => 'otro'], 'csv', $jefa)],
          ['rango de 93 días', fn ($c) => $c->ejecutar('censo', ['desde' => '2026-07-01', 'hasta' => '2026-10-02'], 'csv', $jefa)], ['reproducir inexistente', fn ($c) => $c->reproducir('exp-x', $jefa)]];
[$ra, $a, $rb, $b] = $mundo(); $dif = [];
foreach ($casos as [$nombre, $f]) { if ($err(fn () => $f($a)) !== $err(fn () => $f($b)) || $err(fn () => $f($a)) === 'sin error') { $dif[] = $nombre; } }
ver('ERR-01', $dif === [], sprintf('Los %d casos de error (rol, reporte, formato, filtros, rango, reproducir) dan el mismo código y campo antes y después%s', count($casos), $dif ? ' · distintos: ' . implode(', ', $dif) : ''));

// Se registra antes de entregar: si el registro falla, no se entrega nada
[$ra, $a, $rb, $b] = $mundo(); $ra->fallarAlGuardar = true; $rb->fallarAlGuardar = true;
ver('ATO-01', $err(fn () => $a->ejecutar('censo', $rango, 'csv', $jefa)) === 'error_persistencia' && $err(fn () => $b->ejecutar('censo', $rango, 'csv', $jefa)) === 'error_persistencia' && $ra->cantidad() === 0 && $rb->cantidad() === 0, 'Si falla el registro, los dos lanzan ErrorPersistencia y no queda ninguna exportación (no se entrega archivo)');

// Una regla nueva de filas, en un solo lugar: vale para exportar y para reproducir
$rc = EnMemoria::demo(); $sinCirugia = fn (array $filas) => array_values(array_filter($filas, fn ($x) => $x['servicio'] !== 'cirugia'));
$c = new ExportarReporteDespues($rc, new PreparadorDeArchivo($rc, catalogo(), ['csv' => new Csv()], $sinCirugia), $reloj); $z = $c->ejecutar('censo', $rango, 'csv', $jefa);
ver('CAM-01', $z['filas'] === 1 && !str_contains($z['contenido'], 'cirugia') && $c->reproducir($z['id'], $jefa)['coincide'], 'Una regla nueva (excluir cirugía) puesta en el preparador se aplica al exportar y al reproducir, y «coincide» sigue siendo verdad');

// Contratos del documento = contratos del código
$doc = (string) @file_get_contents(__DIR__ . '/semana-07-componentes-refactor.md'); $app = $ruta("$micro/src/Aplicacion/ExportarReporte.php");
preg_match_all("/return \[('id' => .*?)\];/", $app, $m); preg_match_all("/'(\w+)' =>/", implode(',', $m[1]), $claves);
$salidas = array_unique($claves[1]); sort($salidas); $faltan = array_filter(array_merge($salidas, ['codigo', 'filtros', 'formato']), fn ($k) => !str_contains($doc, "`$k`"));
$rutas = $ruta("$micro/src/Presentacion/Enrutador.php"); preg_match_all('#/api/v1/[\w/{}]+#', str_replace(['(\w+)', '([\w-]+)'], ['{codigo}', '{id}'], $rutas), $rt);
$sinRuta = array_filter(array_unique($rt[0]), fn ($r) => !str_contains($doc, $r));
ver('CON-01', $salidas === ['coincide', 'contenido', 'filas', 'hash', 'id'] && $faltan === [] && count(array_unique($rt[0])) === 2 && $sinRuta === [], 'El documento describe las 5 claves de salida, las 3 de entrada y las 2 rutas de la API que tiene el código');
$img = array_filter(['componentes', 'refactor-antes-despues'], fn ($n) => is_file(__DIR__ . "/diagramas/semana-07-$n.png") && is_file(__DIR__ . "/diagramas/semana-07-$n.puml"));
ver('DOC-01', count($img) === 2 && $doc !== '', 'Existen el documento y los 2 diagramas (componentes y antes/después) con su fuente');
echo $fallas === 0 ? "\nResultado: el refactor conserva el comportamiento y deja un solo camino.\n" : "\nResultado: $fallas comprobaciones fallan.\n";
exit($fallas ? 1 : 0);

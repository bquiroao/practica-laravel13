<?php
declare(strict_types=1);

// Evidencia de la semana 2. Uso: php verificar.php   (sale con 1 si algo no da lo esperado)
require __DIR__ . '/antes.php';
require __DIR__ . '/despues.php';

use Reportes\Antes as A;
use Reportes\Despues as D;

$fallas = 0;
function ver(string $id, bool $ok, string $detalle): void {
    $GLOBALS['fallas'] += $ok ? 0 : 1;
    printf("%-7s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $detalle);
}
function codigoDe(callable $f): string {
    try { $f(); return 'sin error'; } catch (D\ErrorFiltro $e) { return "filtro_invalido:$e->campo"; } catch (\DomainException $e) { return $e->getMessage(); }
}
$reloj = fn () => '2026-10-26T10:00';
$rango = ['desde' => '2026-10-01', 'hasta' => '2026-10-26'];
$tardia = ['paciente' => 'Eva Ruiz', 'documento' => '5555', 'especialidad' => 'pediatria', 'estado' => 'atendida', 'fecha' => '2026-10-24', 'registrado_en' => '2026-10-27T09:00'];

echo "== OCP: ¿el núcleo está cerrado?\n";
$fuente = fn (string $clase) => implode('', array_slice(file((new ReflectionClass($clase))->getFileName()), (new ReflectionClass($clase))->getStartLine() - 1,
    (new ReflectionClass($clase))->getEndLine() - (new ReflectionClass($clase))->getStartLine() + 1));
$cases = preg_match_all('/\bcase\b/', $fuente(A\ExportadorReportes::class));
$nombres = preg_match_all("/'(censo|egresos|citas|csv|json)'|\b(switch|match)\b/", $fuente(D\ExportadorReproducible::class));
ver('OCP-01', $cases === 5 && $nombres === 0, "antes: $cases ramas case y cada reporte o formato nuevo agrega otra · después: el núcleo no nombra reportes ni formatos ($nombres)");

$e = D\exportadorEstandar($reloj);
$e->registrarFormato(new class implements D\FormatoExportacion {   // extensión: formato nuevo sin tocar despues.php
    public function codigo(): string { return 'tsv'; }
    public function generar(array $columnas, array $filas): string { return implode("\n", array_map(fn ($f) => implode("\t", $f), array_merge([$columnas], $filas))); }
});
$e->agregarFuente('estancias', [['servicio' => 'medicina', 'dias' => 4, 'fecha' => '2026-10-25', 'registrado_en' => '2026-10-25T12:00']]);
$e->registrarReporte(new D\ReporteTabular('estancias', 'estancias', ['servicio' => false, 'dias' => false, 'fecha' => false]));  // extensión: reporte nuevo
$x = $e->exportar('estancias', $rango, 'tsv', false);
ver('OCP-02', $x['contenido'] === "servicio\tdias\tfecha\nmedicina\t4\t2026-10-25" && $e->reproducir($x['id']), 'Reporte «estancias» y formato TSV agregados desde afuera: funcionan y se reproducen');
ver('OCP-03', codigoDe(fn () => (new A\ExportadorReportes(D\fuentesEjemplo(), '2026-10-26T10:00'))->exportar('censo', $rango, 'tsv', true)) === 'formato_no_soportado',
    'antes: TSV no existe y para agregarlo hay que modificar ExportadorReportes');

echo "== Defectos que el diseño anterior producía\n";
$viejo = new A\ExportadorReportes(D\fuentesEjemplo(), '2026-10-26T10:00');
$v = $viejo->exportar('citas', $rango, 'csv', true);
$viejo->fuentes['citas'][] = $tardia;
$nuevo = D\exportadorEstandar($reloj);
$n = $nuevo->exportar('citas', $rango, 'csv', true);
$nuevo->agregarFuente('citas', array_merge(D\fuentesEjemplo()['citas'], [$tardia]));
ver('DEF-01', !$viejo->reproducir($v['id']) && $nuevo->reproducir($n['id']), 'Una cita registrada después del corte: antes la reproducción da otro hash · después coincide');
$egresoViejo = (new A\ExportadorReportes(D\fuentesEjemplo(), '2026-10-26T10:00'))->exportar('egresos', $rango, 'csv', false)['contenido'];
$egresoNuevo = D\exportadorEstandar($reloj)->exportar('egresos', $rango, 'csv', false)['contenido'];
ver('DEF-02', str_contains($egresoViejo, '3333') && !str_contains($egresoNuevo, '3333'), 'Egresos sin permiso de datos personales: antes sale el documento · después sale ***');

echo "== Criterios de aceptación\n";
$exp = fn (string $r, array $fl, string $fmt = 'csv') => D\exportadorEstandar($reloj)->exportar($r, $fl, $fmt, true);
ver('CA-03', codigoDe(fn () => $exp('censo', ['desde' => '2026-06-01', 'hasta' => '2026-10-26'])) === 'filtro_invalido:hasta', 'Rango de más de 92 días → filtro_invalido en «hasta»');
ver('CA-04', codigoDe(fn () => $exp('censo', ['desde' => '2026-10-20', 'hasta' => '2026-10-10'])) === 'filtro_invalido:desde', 'desde mayor que hasta → filtro_invalido en «desde»');
ver('CA-05', codigoDe(fn () => $exp('censo', ['desde' => '2026-10-20', 'hasta' => '2026-10-30'])) === 'filtro_invalido:hasta', 'Fecha futura → filtro_invalido');
ver('CA-06', codigoDe(fn () => $exp('egresos', $rango + ['servicio' => 'medicina'])) === 'filtro_invalido:servicio', 'Filtro que el reporte no admite → filtro_invalido');
ver('CA-07', codigoDe(fn () => $exp('censo', $rango, 'xlsx')) === 'formato_no_soportado', 'Formato no registrado → formato_no_soportado');
ver('CA-08', $exp('censo', $rango + ['servicio' => 'medicina'])['hash'] === $exp('censo', ['Servicio' => ' MEDICINA ', 'hasta' => '2026-10-26', 'desde' => '2026-10-01'])['hash'],
    'Mismos filtros en otro orden, con mayúsculas y espacios → mismo hash');
ver('CA-09', ($vacio = $exp('censo', ['desde' => '2026-09-01', 'hasta' => '2026-09-30']))['filas'] === 0 && $vacio['contenido'] === 'paciente,documento,servicio,cama,fecha',
    'Sin datos → archivo solo con encabezados, registrado igual');

echo $fallas === 0 ? "\nResultado: todo lo esperado se cumple.\n" : "\nResultado: $fallas verificaciones fallaron.\n";
exit($fallas === 0 ? 0 : 1);

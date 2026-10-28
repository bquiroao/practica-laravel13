<?php
declare(strict_types=1);

// Pruebas sin dependencias:  php tests/ejecutar.php   (usa SQLite en memoria como base de prueba)
use Reportes\Aplicacion\{ErrorPersistencia, ExportarReporte, Usuario};
use Reportes\Dominio\{Csv, ErrorDominio};
use Reportes\Persistencia\ReportesPdo;
use Reportes\Presentacion\ReportesControlador;
use function Reportes\Dominio\catalogo;

require __DIR__ . '/../bootstrap.php';

$conteo = ['PASA' => 0, 'FALLA' => 0];
function prueba(string $id, string $que, callable $cuerpo): void {
    try { $cuerpo(); $estado = 'PASA'; $detalle = ''; } catch (\Throwable $e) { $estado = 'FALLA'; $detalle = ' → ' . $e->getMessage(); }
    $GLOBALS['conteo'][$estado]++;
    printf("%-7s %-5s %s%s\n", $id, $estado, $que, $detalle);
}
function igual(mixed $esperado, mixed $real, string $que = 'valor'): void {
    if ($esperado !== $real) { throw new \RuntimeException("$que: esperado " . json_encode($esperado) . ', obtenido ' . json_encode($real)); }
}
function errorDe(callable $f): \Throwable {
    try { $f(); } catch (\Throwable $e) { return $e; }
    throw new \RuntimeException('se esperaba un error');
}
function base(): PDO {
    $pdo = new PDO('sqlite::memory:');
    $pdo->exec((string) file_get_contents(__DIR__ . '/../sql/esquema.sql'));
    return $pdo;
}
function caso(PDO $pdo): ExportarReporte { return new ExportarReporte(new ReportesPdo($pdo), catalogo(), ['csv' => new Csv()], fn () => '2026-10-25 12:00:00'); }
function registros(PDO $pdo): int { return (int) $pdo->query('SELECT COUNT(*) FROM exportaciones_reporte')->fetchColumn(); }
$jefa = new Usuario('usr-jefa', 'jefatura', 'hosp-1', true);
$auditor = new Usuario('usr-aud', 'auditor', 'hosp-1', false);
$rango = ['desde' => '2026-10-01', 'hasta' => '2026-10-25'];

prueba('APP-01', 'Camino feliz: censo del hosp-1, columnas del catálogo, orden estable, hash y registro guardado', function () use ($jefa, $rango) {
    $pdo = base();
    $r = caso($pdo)->ejecutar('censo', $rango, 'csv', $jefa);
    igual("paciente,documento,servicio,cama,fecha\nAna López,1111,medicina,A1,2026-10-20\nLuis Pérez,2222,cirugia,B2,2026-10-21", $r['contenido'], 'archivo');
    igual([2, hash('sha256', $r['contenido']), 1], [$r['filas'], $r['hash'], registros($pdo)], 'filas, hash y registro');
    igual('{"desde":"2026-10-01","hasta":"2026-10-25"}', $pdo->query('SELECT filtros FROM exportaciones_reporte')->fetchColumn(), 'filtros guardados');
});

prueba('DOM-01', 'Regla de dominio: más de 92 días, filtro no permitido o fecha futura → filtro_invalido y nada se registra', function () use ($jefa) {
    $pdo = base();
    $casos = [[['desde' => '2026-01-01', 'hasta' => '2026-10-25'], 'hasta'], [['desde' => '2026-10-01', 'hasta' => '2026-10-25', 'tipo_alta' => 'medica'], 'tipo_alta'],
              [['desde' => '2026-10-01', 'hasta' => '2026-10-26'], 'hasta'], [['hasta' => '2026-10-25'], 'desde']];
    foreach ($casos as [$filtros, $campo]) {
        $e = errorDe(fn () => caso($pdo)->ejecutar('censo', $filtros, 'csv', $jefa));
        igual([ErrorDominio::class, 'filtro_invalido', $campo], [$e::class, $e->codigo, $e->campo], 'error');
    }
    igual(0, registros($pdo), 'registros');
});

prueba('DOM-02', 'Sin permiso de datos personales se enmascaran paciente y documento; filtro normalizado " Cirugia "', function () use ($auditor, $rango) {
    $r = caso(base())->ejecutar('censo', $rango + ['servicio' => ' Cirugia '], 'csv', $auditor);
    igual("paciente,documento,servicio,cama,fecha\n***,***,cirugia,B2,2026-10-21", $r['contenido'], 'archivo');
});

prueba('PER-01', 'Error de persistencia: sin tabla de registro → ErrorPersistencia y no se entrega archivo; HTTP 500', function () use ($jefa, $rango) {
    $pdo = base();
    $pdo->exec('DROP TABLE exportaciones_reporte');
    $e = errorDe(fn () => caso($pdo)->ejecutar('censo', $rango, 'csv', $jefa));
    igual([ErrorPersistencia::class, PDOException::class], [$e::class, $e->getPrevious()::class], 'error envuelto');
    [$estado, $cuerpo] = (new ReportesControlador(caso($pdo), ['jefa' => $jefa]))->atender('GET', '/api/v1/reportes/censo/exportar', 'jefa', $rango);
    igual([500, 'error_persistencia'], [$estado, $cuerpo['codigo']], 'respuesta');
});

prueba('APP-02', 'Reproducible: una fila registrada después del corte no cambia el hash al reproducir', function () use ($jefa, $rango) {
    $pdo = base();
    $r = caso($pdo)->ejecutar('censo', $rango, 'csv', $jefa);
    $pdo->exec("INSERT INTO vw_censo_servicio VALUES ('hosp-1', 'Tardía', '4444', 'medicina', 'A9', '2026-10-24', '2026-10-25 15:00:00')");
    $otra = new ExportarReporte(new ReportesPdo($pdo), catalogo(), ['csv' => new Csv()], fn () => '2026-10-26 09:00:00');
    igual([true, $r['hash']], [$otra->reproducir($r['id'], $jefa)['coincide'], $otra->reproducir($r['id'], $jefa)['hash']], 'reproducción');
    igual(3, $otra->ejecutar('censo', $rango, 'csv', $jefa)['filas'], 'una exportación nueva sí ve la fila');
});

prueba('HTTP-01', 'Controlador: 200 con cabeceras, 401, 403, 404, 405, 400 y 422', function () use ($jefa, $rango) {
    $c = new ReportesControlador(caso(base()), ['jefa' => $jefa, 'enf' => new Usuario('usr-enf', 'enfermeria', 'hosp-1', false)]);
    [$estado, $cuerpo, $cab] = $c->atender('GET', '/api/v1/reportes/censo/exportar', 'jefa', $rango + ['formato' => 'csv']);
    igual([200, 'text/csv; charset=utf-8', hash('sha256', $cuerpo)], [$estado, $cab['Content-Type'], $cab['X-Reporte-Sha256']], 'exportación');
    $estados = [['GET', '/api/v1/reportes/censo/exportar', 'nadie', $rango], ['GET', '/api/v1/reportes/censo/exportar', 'enf', $rango],
                ['GET', '/api/v1/reportes/farmacia/exportar', 'jefa', $rango], ['POST', '/api/v1/reportes/censo/exportar', 'jefa', $rango],
                ['GET', '/api/v1/reportes/censo/exportar', 'jefa', $rango + ['formato' => 'pdf']], ['GET', '/api/v1/reportes/censo/exportar', 'jefa', ['desde' => '2026-10-25', 'hasta' => '2026-10-01']]];
    igual([401, 403, 404, 405, 400, 422], array_map(fn ($p) => $c->atender(...$p)[0], $estados), 'estados');
});

prueba('ARQ-01', 'Dependencias: Dominio no conoce otras capas ni PDO; Aplicación no conoce HTTP ni SQL; Presentación no conoce PDO', function () {
    $prohibido = ['Dominio' => '/Reportes\\\\(Aplicacion|Persistencia|Presentacion)|\\\\PDO\b|SELECT |\$_(GET|POST)/',
                  'Aplicacion' => '/Reportes\\\\Presentacion|\\\\PDO\b|SELECT |INSERT |\$_(GET|POST)|header\(/',
                  'Presentacion' => '/Reportes\\\\Persistencia|\\\\PDO\b|SELECT |INSERT /',
                  'Persistencia' => '/Reportes\\\\Presentacion|\$_(GET|POST)/'];
    foreach ($prohibido as $capa => $patron) {
        foreach (glob(__DIR__ . "/../src/$capa/*.php") as $archivo) {
            if (preg_match($patron, (string) file_get_contents($archivo), $m)) { throw new \RuntimeException("$capa/" . basename($archivo) . " usa «{$m[0]}»"); }
        }
    }
});

printf("\nPasan: %d  Fallan: %d\n", $conteo['PASA'], $conteo['FALLA']);
exit($conteo['FALLA'] === 0 ? 0 : 1);

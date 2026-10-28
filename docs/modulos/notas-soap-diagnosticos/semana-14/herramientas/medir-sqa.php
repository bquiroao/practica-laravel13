<?php

declare(strict_types=1);

/**
 * Semana 14 · Mide las 7 métricas del plan SQA sobre el Micro-HIS real y las compara con su umbral.
 * Uso: php herramientas/medir-sqa.php   (desde semana-14). Con SQA_MICROHIS=<carpeta> mide otra copia (inyección de defectos).
 * Termina con código 1 si alguna métrica queda en ROJO.
 */
$micro = getenv('SQA_MICROHIS') ?: dirname(__DIR__, 2) . '/micro-his-notas-soap';
require $micro . '/bootstrap/contenedor.php';
foreach (glob($micro . '/tests/Dobles/*.php') as $archivo) {
    require_once $archivo;
}

use NotasSoap\Dominio\Modelo\{NotaSoap, SeccionesSoap};
use NotasSoap\Persistencia\EnMemoria\AlmacenesEnMemoria;
use NotasSoap\Pruebas\Dobles\{Escenario, GeneradorSecuencial, RelojFijo, RepositorioDiagnosticosQueFalla, BandejaSalidaQueFalla, VerificadorAccesoQueExplota, RegistroAccesoQueFalla};

$m = [];
$pct = static fn (int $a, int $b): string => sprintf('%.1f %% (%d/%d)', $b ? 100 * $a / $b : 0, $a, $b);
$firmadaEnAlmacen = static function (Escenario $e): string {
    $e->notas->guardar(NotaSoap::registrar('nota-sqa', 'exp-carlos', 'epi-100', 'dra-mendez', new SeccionesSoap('Tos', 'T 38 °C', 'Bronquitis', 'Control'),
        new DateTimeImmutable('2026-10-01T08:00:00-06:00'))->firmar(new DateTimeImmutable('2026-10-01T08:10:00-06:00')));
    return 'nota-sqa';
};

// M1 · Pruebas en verde
preg_match('/Total: (\d+)\s+Pasan: (\d+)\s+Fallan: (\d+)\s+Omitidas: (\d+)/', (string) shell_exec('cd ' . escapeshellarg($micro) . ' && php tests/ejecutar.php'), $s);
[, $total, $pasan, $fallan, $omitidas] = array_map('intval', $s + [0, 0, 0, 1, 0]);
$m['M1'] = ['Pruebas automáticas en verde', $fallan > 0 ? 'ROJO' : ($omitidas > 1 ? 'ÁMBAR' : 'VERDE'), $pct($pasan, $total - $omitidas) . ", $omitidas omitida(s)"];

// M2 · Códigos de error del contrato que alguna prueba afirma
$yaml = (string) file_get_contents("$micro/api/openapi.yaml");
preg_match('/            code:\n              type: string\n              enum:\n((?:                - \S+\n)+)/', $yaml, $e);
preg_match_all('/- (\S+)/', $e[1] ?? '', $c);
$pruebas = implode("\n", array_map('file_get_contents', glob("$micro/tests/*/*Prueba.php")));
$sin = array_values(array_filter($c[1], static fn (string $cod): bool => !str_contains($pruebas, "'$cod'")));
$m['M2'] = ['Códigos de error afirmados por pruebas', $sin ? 'ROJO' : 'VERDE', $pct(count($c[1]) - count($sin), count($c[1])) . ($sin ? '  → faltan ' . implode(', ', $sin) : '')];

// M3 · Conformidad contrato ↔ código (método, ruta y x-permiso)
preg_match_all('#^  (/\S+):\n    (get|post):\n(?:      .*\n)*?      x-permiso: (\S+)#m', $yaml, $ops, PREG_SET_ORDER);
$contrato = array_map(static fn ($o) => strtoupper($o[2]) . " $o[1] $o[3]", $ops);
$codigo = array_map(static fn ($r) => "$r->metodo $r->plantilla $r->permiso", (new Escenario())->kernel->rutas());
$iguales = count(array_intersect($codigo, $contrato));
$m['M3'] = ['Conformidad contrato ↔ código', $iguales === count($codigo) && count($contrato) === count($codigo) ? 'VERDE' : 'ROJO', $pct($iguales, count($codigo)) . ' operaciones'];

// M4 · Integridad ante fallas inyectadas (R-06): sin notas, firmas ni eventos a medias
$ev = static fn (Escenario $x): array => array_column($x->auditoria->eventos(), 'evento');
$casos = [
    static fn () => ($x = new Escenario(diagnosticos: new RepositorioDiagnosticosQueFalla())) && $x->kernel->manejar(Escenario::peticion())->estado === 500 && !$x->notas->todas() && !$ev($x),
    static function () use ($firmadaEnAlmacen, $ev) { $x = new Escenario(diagnosticos: new RepositorioDiagnosticosQueFalla()); $id = $firmadaEnAlmacen($x);
        return $x->kernel->manejar(Escenario::peticionCorreccion($id))->estado === 500 && count($x->notas->todas()) === 1 && !$ev($x); },
    static function () use ($ev) { $x = new Escenario(bandeja: new BandejaSalidaQueFalla()); $id = $x->kernel->manejar(Escenario::peticion())->cuerpo['clinical_note_id'];
        return $x->kernel->manejar(Escenario::peticion([], [], 'POST', "/api/v1/notas-soap/$id/firma"))->estado === 500 && $x->notas->buscar($id)->estado === 'draft' && $ev($x) === ['NOTA_REGISTRADA']; },
    static fn () => ($x = new Escenario(acceso: new VerificadorAccesoQueExplota())) && $x->kernel->manejar(Escenario::peticion())->estado === 500 && !$x->notas->todas(),
    static fn () => ($x = new Escenario(registroAcceso: new RegistroAccesoQueFalla())) && $x->kernel->manejar(Escenario::peticion())->estado === 201 && count($x->notas->todas()) === 1,
];
$ok4 = count(array_filter($casos, static fn (callable $f): bool => (bool) $f()));
$m['M4'] = ['Integridad ante fallas inyectadas (R-06)', $ok4 === count($casos) ? 'VERDE' : 'ROJO', $pct($ok4, count($casos)) . ' escenarios'];

// M5 · Aislamiento por hospital (R-01): intentos de un médico de otro hospital que el servidor rechaza
$config = require "$micro/config/config.php";
$config['tokens_demo']['token-central'] = ['dr-central', 'hosp-central', ['medico']];
$al = new AlmacenesEnMemoria(require "$micro/config/datos-demo-memoria.php");
$k = crearKernelEnMemoria($config, $al, new GeneradorSecuencial(), new RelojFijo());
$borrador = $k->manejar(Escenario::peticion())->cuerpo['clinical_note_id'];
$firmada = Escenario::notaFirmada($k);
$otro = ['authorization' => 'Bearer token-central', 'x-tenant-id' => 'hosp-central'];
$intentos = [Escenario::peticion([], $otro, 'GET', "/api/v1/notas-soap/$borrador"), Escenario::peticion([], $otro, 'GET', '/api/v1/expedientes/exp-carlos/notas-soap'),
    Escenario::peticion([], $otro, 'POST', "/api/v1/notas-soap/$borrador/firma"), Escenario::peticionCorreccion($firmada, [], $otro), Escenario::peticion([], $otro)];
$rechazos = count(array_filter($intentos, static fn ($p): bool => in_array($k->manejar($p)->estado, [403, 404], true)));
$m['M5'] = ['Aislamiento por hospital (R-01)', $rechazos === count($intentos) ? 'VERDE' : 'ROJO', $pct($rechazos, count($intentos)) . ' intentos rechazados'];

// M6 · Hallazgos Crítico/Mayor de la revisión S13 sin verificar
$csv = dirname(__DIR__, 2) . '/semana-13/plantillas/registro-hallazgos.csv';
$filas = is_file($csv) ? array_map('str_getcsv', array_slice(file($csv, FILE_IGNORE_NEW_LINES), 1)) : null;
$abiertos = static fn (string $sev): int => count(array_filter($filas ?? [], static fn ($f) => ($f[6] ?? '') === $sev && !in_array($f[11] ?? '', ['Verificado', 'Rechazado'], true)));
$m['M6'] = ['Hallazgos Crítico/Mayor sin verificar', $filas === null ? 'ÁMBAR' : ($abiertos('Crítico') + $abiertos('Mayor') > 0 ? 'ROJO' : 'VERDE'),
    $filas === null ? 'sin registro de la S13' : sprintf('%d (Crítico %d · Mayor %d)', $abiertos('Crítico') + $abiertos('Mayor'), $abiertos('Crítico'), $abiertos('Mayor'))];

// M7 · Escrituras cuyo reintento idéntico no duplica efectos, tal como el contrato permite llamarlas
$reintento = static function (callable $preparar): bool { $x = new Escenario(); $p = $preparar($x); $antes = count($x->notas->todas());
    $x->kernel->manejar($p); $x->kernel->manejar($p); return count($x->notas->todas()) - $antes <= 1; };
$escrituras = [
    'registrar sin clave' => static fn () => Escenario::peticion(),
    'registrar con clave' => static fn () => Escenario::peticion([], ['idempotency-key' => 'sqa-reintento-01']),
    'firmar' => static fn (Escenario $x) => Escenario::peticion([], [], 'POST', '/api/v1/notas-soap/' . $x->kernel->manejar(Escenario::peticion())->cuerpo['clinical_note_id'] . '/firma'),
    'corregir con clave' => static fn (Escenario $x) => Escenario::peticionCorreccion(Escenario::notaFirmada($x->kernel), [], ['idempotency-key' => 'sqa-reintento-02']),
];
$seguras = array_keys(array_filter($escrituras, $reintento));
$m['M7'] = ['Escrituras con reintento seguro', count($seguras) === count($escrituras) ? 'VERDE' : 'ROJO',
    $pct(count($seguras), count($escrituras)) . (count($seguras) < count($escrituras) ? '  → no seguro: ' . implode(', ', array_diff(array_keys($escrituras), $seguras)) : '')];

$conteo = array_count_values(array_column($m, 1));
foreach ($m as $id => [$nombre, $estado, $valor]) {
    printf("%-6s %s  %-42s %s\n", $estado, $id, $nombre, $valor);
}
printf("\nVERDE: %d  ÁMBAR: %d  ROJO: %d\n", $conteo['VERDE'] ?? 0, $conteo['ÁMBAR'] ?? 0, $conteo['ROJO'] ?? 0);
exit(isset($conteo['ROJO']) ? 1 : 0);

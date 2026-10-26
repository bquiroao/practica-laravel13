<?php
declare(strict_types=1);

// Pruebas sin dependencias:  php tests/ejecutar.php   (usa SQLite en memoria como base de prueba)
use SignosVitales\Aplicacion\{ErrorPersistencia, RegistrarToma, Usuario};
use SignosVitales\Dominio\ErrorDominio;
use SignosVitales\Persistencia\TomasPdo;
use SignosVitales\Presentacion\TomasControlador;

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
function filas(PDO $pdo, string $tabla): int { return (int) $pdo->query("SELECT COUNT(*) FROM $tabla")->fetchColumn(); }
function caso(PDO $pdo): RegistrarToma { return new RegistrarToma(new TomasPdo($pdo), fn () => new DateTimeImmutable('2026-10-25 08:00'), fn () => 'toma-1'); }
$ana = new Usuario('enf-ana', 'enfermeria', 'hosp-1');
$toma = fn (array $mediciones, string $paciente = 'pac-adulto', string $episodio = 'epi-1') => ['paciente_id' => $paciente, 'episodio_id' => $episodio, 'mediciones' => $mediciones];

prueba('APP-01', 'Camino feliz: FC 135 crítica, SpO2 97 y 36.8 °C normales; se guarda todo y la crítica queda para M21', function () use ($ana, $toma) {
    $pdo = base();
    $r = caso($pdo)->ejecutar($toma(['fc' => 135, 'spo2' => 97, 'temp' => 36.8]), $ana);
    igual(['fc' => 'critica', 'spo2' => 'normal', 'temp' => 'normal'], $r['clasificaciones'], 'clasificaciones');
    igual([['signo' => 'fc', 'nivel' => 'critica', 'valor' => 135.0]], $r['alertas'], 'alertas');
    igual([1, 3, 1], [filas($pdo, 'tomas_signos_vitales'), filas($pdo, 'mediciones_signo'), filas($pdo, 'alertas_signos_vitales')], 'filas');
    igual('pendiente_envio', $pdo->query('SELECT estado_envio FROM alertas_signos_vitales')->fetchColumn(), 'estado de envío');
});
prueba('DOM-01', 'Regla de dominio: FC 400 es imposible → medicion_invalida en «fc» y no se guarda nada', function () use ($ana, $toma) {
    $pdo = base();
    $e = errorDe(fn () => caso($pdo)->ejecutar($toma(['fc' => 400, 'temp' => 36.8]), $ana));
    igual([ErrorDominio::class, 'medicion_invalida', ['fc']], [$e::class, $e->codigo, $e->campos], 'error');
    igual(0, filas($pdo, 'tomas_signos_vitales'), 'tomas');
});
prueba('DOM-02', 'Paciente de 5 años sin rango cargado → sin_rango y sin alerta (no inventa umbral)', function () use ($ana, $toma) {
    igual([['fc' => 'sin_rango'], []], array_values(array_slice(caso(base())->ejecutar($toma(['fc' => 150], 'pac-nino', 'epi-2'), $ana), 1)));
});
prueba('PER-01', 'Error de persistencia: falla el INSERT de alertas → ErrorPersistencia y rollback (0 tomas, 0 mediciones)', function () use ($ana, $toma) {
    $pdo = base();
    $pdo->exec('DROP TABLE alertas_signos_vitales');
    igual(ErrorPersistencia::class, errorDe(fn () => caso($pdo)->ejecutar($toma(['fc' => 135]), $ana))::class, 'error');
    igual([0, 0], [filas($pdo, 'tomas_signos_vitales'), filas($pdo, 'mediciones_signo')], 'filas');
});
prueba('APP-02', 'Episodio cerrado → 409; médico o enfermería de otro hospital → acceso_denegado', function () use ($ana, $toma) {
    igual('episodio_no_disponible', errorDe(fn () => caso(base())->ejecutar($toma(['fc' => 80], 'pac-adulto', 'epi-cerrado'), $ana))->codigo);
    igual('acceso_denegado', errorDe(fn () => caso(base())->ejecutar($toma(['fc' => 80]), new Usuario('dr-luis', 'medico', 'hosp-1')))->codigo);
    igual('acceso_denegado', errorDe(fn () => caso(base())->ejecutar($toma(['fc' => 80]), new Usuario('enf-eva', 'enfermeria', 'hosp-2')))->codigo);
});
prueba('HTTP-01', 'Presentación: 201, 401, 400, 404, 405 y 422 con su código', function () use ($ana) {
    $pdo = base();
    $api = new TomasControlador(caso($pdo), ['enf-ana' => $ana]);
    $ruta = '/api/v1/signos-vitales/tomas';
    $ok = '{"paciente_id":"pac-adulto","episodio_id":"epi-1","mediciones":{"fc":72}}';
    igual(201, $api->atender('POST', $ruta, 'enf-ana', $ok)[0], 'registro');
    igual([401, 400, 404, 405], [$api->atender('POST', $ruta, '', $ok)[0], $api->atender('POST', $ruta, 'enf-ana', '{')[0],
                                 $api->atender('POST', '/x', 'enf-ana', $ok)[0], $api->atender('GET', $ruta, 'enf-ana', '')[0]], 'errores');
    $r = $api->atender('POST', $ruta, 'enf-ana', str_replace('72', '"alto"', $ok));
    igual([422, 'medicion_invalida'], [$r[0], $r[1]['codigo']], '422');
});
prueba('ARQ-01', 'Dependencias: Dominio no usa otras capas ni PDO; Aplicación y Presentación no tienen SQL', function () {
    $codigo = fn (string $capa) => implode("\n", array_map('file_get_contents', glob(__DIR__ . "/../src/$capa/*.php")));
    igual(0, preg_match('/Aplicacion|Persistencia|Presentacion|PDO/', $codigo('Dominio')), 'Dominio');
    igual(0, preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b/', $codigo('Aplicacion') . $codigo('Presentacion')), 'SQL fuera de Persistencia');
});

printf("\nTotal: %d  Pasan: %d  Fallan: %d\n", array_sum($conteo), $conteo['PASA'], $conteo['FALLA']);
exit($conteo['FALLA'] === 0 ? 0 : 1);

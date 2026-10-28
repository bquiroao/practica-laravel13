<?php
declare(strict_types=1);

// Pruebas sin dependencias:  php tests/ejecutar.php   (usa SQLite en memoria como base de prueba)
use SignosVitales\Aplicacion\{ConsultarTomas, ErrorPersistencia, RegistrarToma, Usuario};
use SignosVitales\Dominio\{ErrorDominio, RangoSigno};
use SignosVitales\Persistencia\{EnMemoria, TomasPdo};
use SignosVitales\Presentacion\{Enrutador, Peticion, TomasControlador};

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
function caso(PDO|TomasPdo|EnMemoria $base): RegistrarToma {
    $repo = $base instanceof PDO ? new TomasPdo($base) : $base;
    return new RegistrarToma($repo, $repo, fn () => new DateTimeImmutable('2026-10-25 08:00'), fn () => 'toma-1');
}
function enMemoria(): EnMemoria { // los mismos datos de demostración de sql/esquema.sql que usan las pruebas
    return new EnMemoria(['epi-1' => ['paciente_id' => 'pac-adulto', 'hospital_id' => 'hosp-1', 'fecha_nacimiento' => '1980-05-10', 'activo' => true]],
                         [[18, 120, ['fc' => new RangoSigno(60, 100, 40, 130), 'spo2' => new RangoSigno(95, 100, 90, 100)]]]);
}
function app(EnMemoria $repo, array $usuarios): Enrutador {
    return new Enrutador(new TomasControlador(caso($repo), new ConsultarTomas($repo, $repo), $usuarios));
}
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
prueba('REP-01', 'Contrato Repository: los adaptadores PDO y en memoria responden igual', function () use ($ana, $toma) {
    $vistas = [];
    foreach (['pdo' => new TomasPdo(base()), 'memoria' => enMemoria()] as $nombre => $repo) {
        caso($repo)->ejecutar($toma(['fc' => 135, 'spo2' => 97]), $ana);
        $vistas[$nombre] = [$repo->episodioActivo('pac-adulto', 'epi-1'), $repo->episodioActivo('pac-nino', 'epi-1'),
                            array_keys(array_intersect_key($repo->rangosPara(40), ['fc' => 1, 'spo2' => 1])), $repo->rangosPara(5), $repo->deEpisodio('epi-1')];
    }
    igual($vistas['pdo'], $vistas['memoria'], 'pdo frente a memoria');
});
prueba('MVC-01', 'Enrutador → controlador → vista: 201, 200, 401, 400, 403, 404, 405, 409 y 422', function () use ($ana) {
    $app = app(enMemoria(), ['enf-ana' => $ana, 'dr-luis' => new Usuario('dr-luis', 'medico', 'hosp-1')]);
    $q = ['paciente_id' => 'pac-adulto', 'episodio_id' => 'epi-1'];
    $pide = fn (string $m, string $cuerpo = '', string $u = 'enf-ana', string $ruta = '/api/v1/signos-vitales/tomas', array $consulta = []) => $app->atender(new Peticion($m, $ruta, $consulta ?: $q, $u, $cuerpo))[0];
    $ok = '{"paciente_id":"pac-adulto","episodio_id":"epi-1","mediciones":{"fc":72}}';
    igual([201, 200, 200], [$pide('POST', $ok), $pide('GET'), $pide('GET', '', 'dr-luis')], 'registrar y consultar');
    igual([401, 400, 403, 404, 405, 409, 422], [$pide('POST', $ok, ''), $pide('POST', '{'), $pide('POST', $ok, 'dr-luis'), $pide('POST', $ok, 'enf-ana', '/x'),
        $pide('DELETE'), $pide('GET', '', 'enf-ana', '/api/v1/signos-vitales/tomas', ['paciente_id' => 'pac-adulto', 'episodio_id' => 'otro']), $pide('POST', str_replace('72', '"alto"', $ok))], 'errores');
});
prueba('PER-02', 'Doble en memoria que falla al guardar → 500 error_persistencia por HTTP', function () use ($ana) {
    $repo = enMemoria();
    $repo->fallarAlGuardar = true;
    $r = app($repo, ['enf-ana' => $ana])->atender(new Peticion('POST', '/api/v1/signos-vitales/tomas', [], 'enf-ana', '{"paciente_id":"pac-adulto","episodio_id":"epi-1","mediciones":{"fc":72}}'));
    igual([500, 'error_persistencia', []], [$r[0], $r[1]['codigo'], $repo->deEpisodio('epi-1')]);
});
prueba('ARQ-01', 'Dependencias: Dominio aislado; Aplicación sin Persistencia ni SQL; controlador sin SQL ni reglas', function () {
    $codigo = fn (string $capa) => implode("\n", array_map('file_get_contents', glob(__DIR__ . "/../src/$capa/*.php")));
    igual(0, preg_match('/Aplicacion|Persistencia|Presentacion|PDO/', $codigo('Dominio')), 'Dominio');
    igual(0, preg_match('/SignosVitales\\\\Persistencia|\\\\PDO\b/', $codigo('Aplicacion')), 'Aplicación → Persistencia');
    igual(0, preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b/', $codigo('Aplicacion') . $codigo('Presentacion')), 'SQL fuera de Persistencia');
    igual(0, preg_match('/Clasificacion|RangoSigno|LIMITES|Toma::/', (string) file_get_contents(__DIR__ . '/../src/Presentacion/TomasControlador.php')), 'reglas en el controlador');
});

printf("\nTotal: %d  Pasan: %d  Fallan: %d\n", array_sum($conteo), $conteo['PASA'], $conteo['FALLA']);
exit($conteo['FALLA'] === 0 ? 0 : 1);

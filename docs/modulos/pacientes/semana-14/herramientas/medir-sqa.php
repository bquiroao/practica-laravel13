<?php

declare(strict_types=1);

/**
 * Semana 14 · Mide las 7 métricas del plan SQA de Pacientes sobre el Micro-HIS real y las compara con su umbral.
 * Uso: php herramientas/medir-sqa.php   (desde docs/modulos/pacientes/semana-14)
 * Código de salida: 0 si ninguna está en ROJO (las ÁMBAR no bloquean), 1 si alguna está en ROJO.
 */
// SQA_MICROHIS permite medir otra copia (p. ej. una con un defecto inyectado, para probar que la métrica lo detecta).
$micro = getenv('SQA_MICROHIS') ?: dirname(__DIR__, 2) . '/micro-his-pacientes';
$modulo = dirname(__DIR__, 2);
require $micro . '/bootstrap/contenedor.php';
foreach (glob($micro . '/tests/Dobles/*.php') as $archivo) {
    require_once $archivo;
}

use Pacientes\Dominio\Modelo\EventoAuditoria;
use Pacientes\Pruebas\Dobles\Escenario;

$metricas = [];
$registrar = static function (string $id, string $nombre, string $valor, string $umbral, string $estado, string $detalle) use (&$metricas): void {
    $metricas[] = compact('id', 'nombre', 'valor', 'umbral', 'estado', 'detalle');
};
$pct = static fn (int $a, int $b): float => $b === 0 ? 0.0 : round(100 * $a / $b, 1);
$http = static fn (object $e, string $metodo, string $ruta, string $token, ?array $cuerpo = null, array $consulta = [], array $cabeceras = [], string $hospital = 'hosp-oriente') =>
    $e->kernel->manejar(Escenario::http($metodo, $ruta, $token, $cuerpo, $consulta, $cabeceras, $hospital));
$ana = Escenario::json(Escenario::datosAna());

// ---------------------------------------------------------------------------------------------
// M1 · Pruebas en verde (las omitidas se informan aparte).
$salida = (string) shell_exec('php ' . escapeshellarg("$micro/tests/ejecutar.php") . ' 2>&1');
preg_match('/Total:\s*(\d+)\s+Pasan:\s*(\d+)\s+Fallan:\s*(\d+)\s+Omitidas:\s*(\d+)/', $salida, $m);
[$total, $pasan, $fallan, $omitidas] = array_map('intval', array_slice($m, 1, 4));
$ejecutadas = $total - $omitidas;
$registrar('M1', 'Pruebas automáticas en verde', $pct($pasan, $ejecutadas) . " % ($pasan/$ejecutadas)", '100 % y omitidas ≤ 2',
    $fallan > 0 ? 'ROJO' : ($omitidas > 2 ? 'ÁMBAR' : 'VERDE'), "omitidas: $omitidas (requieren PostgreSQL)");

// ---------------------------------------------------------------------------------------------
// M2 · Cobertura de códigos de error: cada código que el dominio o la aplicación devuelve está afirmado en una prueba.
$codigos = [];
foreach (['src/Dominio', 'src/Aplicacion'] as $dir) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$micro/$dir", FilesystemIterator::SKIP_DOTS)) as $f) {
        if (preg_match_all("/(?:new ErrorDominio|parent::__construct)\([^,]+,\s*'([A-Z][A-Z_]{4,})'/", (string) file_get_contents((string) $f), $mm)) {
            $codigos = array_merge($codigos, $mm[1]);
        }
    }
}
$codigos = array_values(array_unique($codigos));
sort($codigos);
$pruebas = '';
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$micro/tests", FilesystemIterator::SKIP_DOTS)) as $f) {
    if (str_ends_with((string) $f, 'Prueba.php')) {
        $pruebas .= file_get_contents((string) $f);
    }
}
$sinPrueba = array_values(array_filter($codigos, static fn (string $c): bool => !str_contains($pruebas, "'$c'")));
$cubiertos = count($codigos) - count($sinPrueba);
$registrar('M2', 'Cobertura de códigos de error del dominio', $pct($cubiertos, count($codigos)) . " % ($cubiertos/" . count($codigos) . ')', '100 %',
    $sinPrueba === [] ? 'VERDE' : 'ROJO', $sinPrueba === [] ? 'todos los códigos están afirmados en alguna prueba' : 'sin prueba: ' . implode(', ', $sinPrueba));

// ---------------------------------------------------------------------------------------------
// M3 · Trazabilidad: cada escritura exitosa deja exactamente 1 evento completo; ninguna fallida deja evento;
//      las búsquedas se auditan sin el criterio en claro.
$e = Escenario::crear();
$casos = [];
$evento = static fn (object $e, string $accion): ?EventoAuditoria => ($l = $e->bitacora->eventos($accion)) === [] ? null : end($l);
$cuenta = static fn (object $e): int => count($e->bitacora->eventos());
$completo = static fn (?EventoAuditoria $ev, array $claves): bool => $ev !== null && $ev->actor !== '' && $ev->hospital !== '' && $ev->paciente !== null
    && array_diff($claves, array_keys($ev->detalle)) === [];
$n = $cuenta($e);
$http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', $ana);
$casos['registro → 1 PACIENTE_REGISTRADO completo'] = $cuenta($e) - $n === 1 && $completo($evento($e, EventoAuditoria::REGISTRADO), ['datos', 'coincidencias_confirmadas', 'motivo', 'motivo_proteccion']);
$n = $cuenta($e);
$http($e, 'PUT', '/api/v1/pacientes/P-000001', 'token-admision-oriente', Escenario::json(Escenario::datosAna(['telefono' => '+502 4444 0001'])), [], ['If-Match' => '"1"']);
$casos['edición → 1 PACIENTE_ACTUALIZADO con antes/después'] = $cuenta($e) - $n === 1 && $completo($evento($e, EventoAuditoria::ACTUALIZADO), ['antes', 'despues', 'motivo']);
$n = $cuenta($e);
$http($e, 'PUT', '/api/v1/pacientes/P-000001', 'token-admision-oriente', Escenario::json(Escenario::datosAna(['nombres' => 'Ana María'])), [], ['If-Match' => '"2"']);
$http($e, 'PUT', '/api/v1/pacientes/P-000001', 'token-admision-oriente', Escenario::json(Escenario::datosAna()), [], ['If-Match' => '"1"']);
$http($e, 'POST', '/api/v1/pacientes', 'token-clinico-oriente', Escenario::json(Escenario::datosLuis()));
$casos['escrituras rechazadas (422, 409, 403) → 0 eventos de escritura'] = count($e->bitacora->eventos(EventoAuditoria::REGISTRADO)) === 1
    && count($e->bitacora->eventos(EventoAuditoria::ACTUALIZADO)) === 1;
$http($e, 'GET', '/api/v1/pacientes', 'token-clinico-oriente', null, ['nombre' => 'lopez perez']);
$busqueda = $evento($e, EventoAuditoria::BUSCADO);
$casos['búsqueda → PACIENTE_BUSCADO sin el criterio en claro'] = $busqueda !== null && !str_contains(json_encode($busqueda->detalle, JSON_UNESCAPED_UNICODE), 'lopez');
$http($e, 'GET', '/api/v1/pacientes/P-000001', 'token-clinico-oriente');
$casos['consulta del detalle → PACIENTE_CONSULTADO con acceso_limitado'] = ($c = $evento($e, EventoAuditoria::CONSULTADO)) !== null && array_key_exists('acceso_limitado', $c->detalle);
$fallidos = array_keys(array_filter($casos, static fn (bool $ok): bool => !$ok));
$registrar('M3', 'Trazabilidad de escrituras y consultas', $pct(count($casos) - count($fallidos), count($casos)) . ' % (' . (count($casos) - count($fallidos)) . '/' . count($casos) . ' escenarios)',
    '100 %', $fallidos === [] ? 'VERDE' : 'ROJO', $fallidos === [] ? 'eventos completos, sin eventos de escrituras rechazadas ni criterios en claro' : 'fallan: ' . implode('; ', $fallidos));

// ---------------------------------------------------------------------------------------------
// M4 · Integridad de la identidad: intentos de duplicar a la misma persona que terminan con UNA sola ficha.
$fichas = static fn (object $e): int => count($e->repositorio->todos());
$intentos = [];
$intento = static function (string $nombre, callable $prueba) use (&$intentos): void {
    $intentos[$nombre] = $prueba(Escenario::crear());
};
$intento('mismo DPI, mismo hospital', static fn (object $e): bool => $http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', $ana)->estado === 201
    && $http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', $ana)->estado === 409 && $fichas($e) === 1);
$intento('mismo DPI con espacios', static fn (object $e): bool => $http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', $ana)->estado === 201
    && $http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', Escenario::json(Escenario::datosAna(['numero_documento' => '2547 89632 0101'])))->estado === 409 && $fichas($e) === 1);
$intento('mismo DPI desde otro hospital', static fn (object $e): bool => $http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', $ana)->estado === 201
    && $http($e, 'POST', '/api/v1/pacientes', 'token-admision-norte', $ana, [], [], 'hosp-norte')->estado === 409 && $fichas($e) === 1);
$intento('reintento con la misma Idempotency-Key', static fn (object $e): bool => $http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', $ana, [], ['Idempotency-Key' => 'sqa-clave-0001'])->estado === 201
    && $http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', $ana, [], ['Idempotency-Key' => 'sqa-clave-0001'])->estado === 201 && $fichas($e) === 1);
$intento('homónima sin documento (sin confirmar)', static fn (object $e): bool => $http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', $ana)->estado === 201
    && $http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', Escenario::json(Escenario::datosAna(['tipo_documento' => null, 'numero_documento' => null])))->estado === 409 && $fichas($e) === 1);
$intento('mismo nombre en mayúsculas y sin tildes', static fn (object $e): bool => $http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', $ana)->estado === 201
    && $http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', Escenario::json(['nombres' => 'ANA LUCIA', 'apellidos' => 'LOPEZ  PEREZ', 'fecha_nacimiento' => '1985-03-14', 'sexo' => 'female']))->estado === 409 && $fichas($e) === 1);
$intento('DPI mal digitado (AR-07): lo frena R-03', static fn (object $e): bool => $http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', $ana)->estado === 201
    && $http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', Escenario::json(Escenario::datosAna(['numero_documento' => '2547896320102'])))->estado === 409 && $fichas($e) === 1);
$intento('editar con el DPI de otro paciente', static fn (object $e): bool => $http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', $ana)->estado === 201
    && $http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', Escenario::json(Escenario::datosLuis()))->estado === 201
    && $http($e, 'PUT', '/api/v1/pacientes/P-000002', 'token-admision-oriente', array_merge(Escenario::json(Escenario::datosLuis()), ['motivo' => 'Corrección', 'documento' => ['tipo' => 'DPI', 'numero' => Escenario::DPI_ANA]]), [], ['If-Match' => '"1"'])->estado === 409);
$fallidos = array_keys(array_filter($intentos, static fn (bool $ok): bool => !$ok));
$registrar('M4', 'Integridad de la identidad (anti-duplicados)', $pct(count($intentos) - count($fallidos), count($intentos)) . ' % (' . (count($intentos) - count($fallidos)) . '/' . count($intentos) . ' intentos bloqueados)',
    '100 %', $fallidos === [] ? 'VERDE' : 'ROJO', $fallidos === [] ? 'ningún intento creó una segunda ficha de la misma persona' : 'fallan: ' . implode('; ', $fallidos));

// ---------------------------------------------------------------------------------------------
// M5 · Confidencialidad y tenant: respuestas a quien NO debe ver, revisadas en busca de datos ajenos.
$e = Escenario::crear();
$sofia = ['nombres' => 'Sofía', 'apellidos' => 'Morales Díaz', 'fecha_nacimiento' => '1994-08-09', 'sexo' => 'female', 'telefono' => '+502 5123 4567',
    'documento' => ['tipo' => 'DPI', 'numero' => '2233445560101']];
$http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', $ana);                                            // P-000001 en hosp-oriente
$http($e, 'POST', '/api/v1/pacientes', 'token-supervisor-oriente', $sofia + ['identidad_protegida' => true, 'motivo' => 'Orden judicial']); // P-000002 protegida
$prohibidoProtegida = ['Sofía', 'Morales', '1994-08-09', '5123', '33445560101'];
$sondas = [
    'clínico busca a la protegida por nombre' => [$http($e, 'GET', '/api/v1/pacientes', 'token-clinico-oriente', null, ['nombre' => 'morales']), $prohibidoProtegida],
    'clínico la busca por código' => [$http($e, 'GET', '/api/v1/pacientes', 'token-clinico-oriente', null, ['codigo' => 'P-000002']), $prohibidoProtegida],
    'clínico abre su detalle' => [$http($e, 'GET', '/api/v1/pacientes/P-000002', 'token-clinico-oriente'), $prohibidoProtegida],
    'admisión la registra otra vez (409)' => [$http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', $sofia), [...$prohibidoProtegida, 'P-000002']],
    'admisión intenta editarla' => [$http($e, 'PUT', '/api/v1/pacientes/P-000002', 'token-admision-oriente', $sofia, [], ['If-Match' => '"1"']), $prohibidoProtegida],
    'otro hospital abre P-000001' => [$http($e, 'GET', '/api/v1/pacientes/P-000001', 'token-admision-norte', null, [], [], 'hosp-norte'), ['P-000001', 'Ana', '0101']],
    'otro hospital registra su DPI (409)' => [$http($e, 'POST', '/api/v1/pacientes', 'token-admision-norte', $ana, [], [], 'hosp-norte'), ['P-000001', '0101']],
    'otro hospital registra a su homónima (409)' => [$http($e, 'POST', '/api/v1/pacientes', 'token-admision-norte',
        Escenario::json(Escenario::datosAna(['tipo_documento' => null, 'numero_documento' => null])), [], [], 'hosp-norte'), ['P-000001']],
];
$fugas = [];
foreach ($sondas as $nombre => [$r, $prohibidos]) {
    $texto = json_encode($r->cuerpo, JSON_UNESCAPED_UNICODE);
    $vistos = array_values(array_filter($prohibidos, static fn (string $p): bool => str_contains($texto, $p)));
    if ($vistos !== []) {
        $fugas[] = "$nombre (" . implode(', ', $vistos) . ')';
    }
}
$registrar('M5', 'Respuestas sin datos de protegidos ni de otro hospital', $pct(count($sondas) - count($fugas), count($sondas)) . ' % (' . (count($sondas) - count($fugas)) . '/' . count($sondas) . ' respuestas limpias)',
    '100 %', $fugas === [] ? 'VERDE' : 'ROJO', $fugas === [] ? 'ninguna respuesta revela datos que el actor no puede ver' : 'fugas: ' . implode('; ', $fugas));

// ---------------------------------------------------------------------------------------------
// M6 · Confiabilidad ante fallas inyectadas: 503, sin ficha a medias, sin evento huérfano, sin clave de idempotencia huérfana.
$fallas = [];
$fallas['base caída al registrar → 503, sin ficha ni evento'] = (function () use ($http, $ana, $fichas): bool {
    $e = Escenario::crear();
    $e->repositorio->fallarEn = ['registrar'];
    return $http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', $ana)->estado === 503 && $fichas($e) === 0 && $e->bitacora->eventos() === [];
})();
$fallas['bitácora caída al registrar → 503 y la ficha se deshace (R-09)'] = (function () use ($http, $ana, $fichas): bool {
    $e = Escenario::crear();
    $e->bitacora->fallar = true;
    return $http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', $ana)->estado === 503 && $fichas($e) === 0;
})();
$fallas['bitácora caída al editar → 503 y la ficha no cambia'] = (function () use ($http, $ana): bool {
    $e = Escenario::crear();
    $http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', $ana);
    $e->bitacora->fallar = true;
    $r = $http($e, 'PUT', '/api/v1/pacientes/P-000001', 'token-admision-oriente', Escenario::json(Escenario::datosAna(['telefono' => '+502 4444 0001'])), [], ['If-Match' => '"1"']);
    $e->bitacora->fallar = false;
    $p = $http($e, 'GET', '/api/v1/pacientes/P-000001', 'token-admision-oriente')->cuerpo['paciente'];
    return $r->estado === 503 && $p['version'] === 1 && $p['telefono'] === '+502 5555 1234';
})();
$fallas['base caída al editar → 503, sin evento'] = (function () use ($http, $ana): bool {
    $e = Escenario::crear();
    $http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', $ana);
    $e->repositorio->fallarEn = ['actualizar'];
    $r = $http($e, 'PUT', '/api/v1/pacientes/P-000001', 'token-admision-oriente', Escenario::json(Escenario::datosAna(['telefono' => '+502 4444 0001'])), [], ['If-Match' => '"1"']);
    return $r->estado === 503 && $e->bitacora->eventos(EventoAuditoria::ACTUALIZADO) === [];
})();
$fallas['lectura caída → 503 sin datos'] = (function () use ($http, $ana): bool {
    $e = Escenario::crear();
    $http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', $ana);
    $e->repositorio->fallarEn = ['porCodigo', 'buscar'];
    $a = $http($e, 'GET', '/api/v1/pacientes/P-000001', 'token-admision-oriente');
    $b = $http($e, 'GET', '/api/v1/pacientes', 'token-admision-oriente', null, ['nombre' => 'lopez']);
    return $a->estado === 503 && $b->estado === 503 && !isset($a->cuerpo['paciente']) && !isset($b->cuerpo['resultados']);
})();
$fallas['falla con Idempotency-Key → la clave no queda guardada y el reintento crea 1 ficha'] = (function () use ($http, $ana, $fichas): bool {
    $e = Escenario::crear();
    $e->bitacora->fallar = true;
    $primero = $http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', $ana, [], ['Idempotency-Key' => 'sqa-clave-0002']);
    $e->bitacora->fallar = false;
    $segundo = $http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', $ana, [], ['Idempotency-Key' => 'sqa-clave-0002']);
    return $primero->estado === 503 && $segundo->estado === 201 && !isset($segundo->cabeceras['Idempotent-Replayed']) && $fichas($e) === 1;
})();
$fallidos = array_keys(array_filter($fallas, static fn (bool $ok): bool => !$ok));
$registrar('M6', 'Confiabilidad ante fallas inyectadas', $pct(count($fallas) - count($fallidos), count($fallas)) . ' % (' . (count($fallas) - count($fallidos)) . '/' . count($fallas) . ')',
    '100 %', $fallidos === [] ? 'VERDE' : 'ROJO', $fallidos === [] ? 'ninguna falla deja fichas, eventos o claves a medias' : 'fallan: ' . implode('; ', $fallidos));

// ---------------------------------------------------------------------------------------------
// M7 · Hallazgos de revisión Crítico/Mayor sin verificar (registro de la S13).
$csv = array_map(static fn (string $l): array => str_getcsv($l, ',', '"', ''), file("$modulo/semana-13/plantillas/registro-hallazgos.csv", FILE_IGNORE_NEW_LINES) ?: []);
$cab = array_map(static fn ($c) => trim((string) $c, "\xEF\xBB\xBF"), array_shift($csv) ?? []);
$abiertos = ['Crítico' => [], 'Mayor' => [], 'Menor' => []];
foreach ($csv as $fila) {
    $h = array_combine($cab, array_pad($fila, count($cab), ''));
    if (isset($abiertos[$h['severidad']]) && $h['estado'] !== 'Verificado' && $h['estado'] !== 'Rechazado') {
        $abiertos[$h['severidad']][] = $h['id'];
    }
}
$cm = count($abiertos['Crítico']) + count($abiertos['Mayor']);
$registrar('M7', 'Hallazgos de revisión Crítico/Mayor sin verificar', "$cm (Crítico " . count($abiertos['Crítico']) . ' · Mayor ' . count($abiertos['Mayor']) . ') · Menor ' . count($abiertos['Menor']),
    '0 Crítico siempre; 0 Mayor para liberar', $cm > 0 ? 'ROJO' : 'VERDE',
    $cm === 0 ? 'sin pendientes' : 'abiertos: ' . implode(', ', array_merge($abiertos['Crítico'], $abiertos['Mayor'])));

// ---------------------------------------------------------------------------------------------
echo 'Plan SQA Pacientes · medición (PHP ' . PHP_VERSION . ')' . PHP_EOL . str_repeat('-', 112) . PHP_EOL;
foreach ($metricas as $x) {
    printf("%-6s %-3s %-50s %s\n       umbral: %s\n       %s\n", $x['estado'], $x['id'], $x['nombre'], $x['valor'], $x['umbral'], $x['detalle']);
}
$rojos = count(array_filter($metricas, static fn ($x): bool => $x['estado'] === 'ROJO'));
$ambar = count(array_filter($metricas, static fn ($x): bool => $x['estado'] === 'ÁMBAR'));
echo str_repeat('-', 112) . PHP_EOL . 'VERDE: ' . (count($metricas) - $rojos - $ambar) . "  ÁMBAR: $ambar  ROJO: $rojos" . PHP_EOL;
exit($rojos > 0 ? 1 : 0);

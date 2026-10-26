<?php

declare(strict_types=1);

/**
 * Semana 14 · Mide las 7 métricas del plan SQA de RBAC sobre el Micro-HIS real y las compara con su umbral.
 * Uso: php herramientas/medir-sqa.php   (desde docs/modulos/rbac/semana-14)
 * Código de salida: 0 si todas están en VERDE, 1 si alguna está en ROJO (las ÁMBAR no bloquean).
 */
// SQA_MICROHIS permite medir otra copia (p. ej. una con un defecto inyectado, para probar que la métrica lo detecta).
$micro = getenv('SQA_MICROHIS') ?: dirname(__DIR__, 2) . '/micro-his-rbac';
$rbac = dirname(__DIR__, 2);
require $micro . '/bootstrap/contenedor.php';
foreach (glob($micro . '/tests/Dobles/*.php') as $archivo) {
    require_once $archivo;
}

use Rbac\Dominio\Modelo\ContextoAutorizacion;
use Rbac\Dominio\Modelo\Membresia;
use Rbac\Dominio\Modelo\SolicitudAutorizacion;
use Rbac\Dominio\Reglas\ReglaAutorizacion;
use Rbac\Dominio\Servicios\PoliticaAutorizacion;
use Rbac\Pruebas\Dobles\Escenario;

$metricas = [];
$registrar = static function (string $id, string $nombre, string $valor, string $umbral, string $estado, string $detalle) use (&$metricas): void {
    $metricas[] = compact('id', 'nombre', 'valor', 'umbral', 'estado', 'detalle');
};
$pct = static fn (int $a, int $b): float => $b === 0 ? 0.0 : round(100 * $a / $b, 1);

// ---------------------------------------------------------------------------------------------
// M1 · Pruebas en verde (las omitidas no cuentan como verdes ni como rojas: se informan aparte).
$salida = (string) shell_exec('php ' . escapeshellarg("$micro/tests/ejecutar.php") . ' 2>&1');
preg_match('/Total:\s*(\d+)\s+Pasan:\s*(\d+)\s+Fallan:\s*(\d+)\s+Omitidas:\s*(\d+)/', $salida, $m);
[$total, $pasan, $fallan, $omitidas] = array_map('intval', array_slice($m, 1, 4));
$ejecutadas = $total - $omitidas;
$registrar('M1', 'Pruebas automáticas en verde', $pct($pasan, $ejecutadas) . " % ($pasan/$ejecutadas)", '100 % y omitidas ≤ 2',
    $fallan > 0 ? 'ROJO' : ($omitidas > 2 ? 'ÁMBAR' : 'VERDE'), "omitidas: $omitidas (requieren PostgreSQL)");

// ---------------------------------------------------------------------------------------------
// M2 · Cobertura de códigos de decisión: cada código que el dominio o la aplicación puede devolver
//      aparece afirmado en al menos una prueba.
$codigos = [];
foreach (['src/Dominio', 'src/Aplicacion'] as $dir) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$micro/$dir", FilesystemIterator::SKIP_DOTS)) as $f) {
        if (preg_match_all("/'([A-Z][A-Z_]{7,})'/", (string) file_get_contents((string) $f), $mm)) {
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
$registrar('M2', 'Cobertura de códigos de decisión', $pct($cubiertos, count($codigos)) . " % ($cubiertos/" . count($codigos) . ')', '100 %',
    $sinPrueba === [] ? 'VERDE' : 'ROJO', $sinPrueba === [] ? 'todos los códigos están afirmados en alguna prueba' : 'sin prueba: ' . implode(', ', $sinPrueba));

// ---------------------------------------------------------------------------------------------
// M3 · Conformidad del contrato: operaciones del código presentes en OpenAPI con el mismo x-permiso.
$yaml = file((string) "$micro/api/openapi.yaml") ?: [];
$doc = [];
$ruta = null;
$metodo = null;
foreach ($yaml as $linea) {
    if (preg_match('#^  (/api/\S+):\s*$#', $linea, $x)) {
        $ruta = $x[1];
    } elseif ($ruta && preg_match('#^    (get|post|put|patch|delete):\s*$#', $linea, $x)) {
        $metodo = strtoupper($x[1]);
        $doc["$metodo $ruta"] = '(sin x-permiso)';
    } elseif ($ruta && $metodo && preg_match('#^      x-permiso:\s*(\S+)#', $linea, $x)) {
        $doc["$metodo $ruta"] = $x[1] === 'null' ? '' : $x[1];
    } elseif (preg_match('#^\S#', $linea)) {
        $ruta = null;
    }
}
$conformes = 0;
$difieren = [];
$rutas = (new Escenario())->rutas();
foreach ($rutas as $r) {
    $path = preg_replace(['/^#\^/', '/\$#$/', '/\(\?<(\w+)>[^)]*\)/'], ['', '', '{$1}'], $r->patron);
    $clave = $r->metodo . ' ' . $path;
    if (($doc[$clave] ?? null) === ($r->permiso ?? '')) {
        $conformes++;
    } else {
        $difieren[] = $clave;
    }
}
$extra = array_diff(array_keys($doc), array_map(static fn ($r): string => $r->metodo . ' ' . preg_replace(['/^#\^/', '/\$#$/', '/\(\?<(\w+)>[^)]*\)/'], ['', '', '{$1}'], $r->patron), $rutas));
$registrar('M3', 'Conformidad contrato ↔ código', $pct($conformes, count($rutas)) . " % ($conformes/" . count($rutas) . ' operaciones)', '100 % y 0 operaciones solo en el contrato',
    $difieren === [] && $extra === [] ? 'VERDE' : 'ROJO', $difieren === [] && $extra === [] ? 'misma ruta, método y x-permiso en las ' . count($rutas) . ' operaciones'
        : 'difieren: ' . implode(', ', array_merge($difieren, $extra)));

// ---------------------------------------------------------------------------------------------
// M4 · Trazabilidad de denegaciones: cada INTENTO denegado deja ACCESO_DENEGADO completo;
//      las consultas PASIVAS no dejan eventos (RNF-14).
$e = new Escenario();
$intentos = [
    ['POST', '/api/v1/prescripciones', 'token-ana', Escenario::H_ORIENTE, null],                                     // permiso no asignado
    ['POST', '/api/v1/roles/asignaciones', 'token-ana', Escenario::H_ORIENTE, ['usuario_id' => 'marta', 'rol' => 'laboratorio', 'valido_desde' => '2026-11-15']],
    ['POST', '/api/v1/roles/revocaciones', 'token-marta', Escenario::H_ORIENTE, ['usuario_id' => 'ana', 'rol' => 'enfermeria', 'motivo' => 'Prueba de trazabilidad']],
    ['GET', '/api/v1/auditoria/eventos/x-123', 'token-ana', Escenario::H_ORIENTE, null],
    ['POST', '/api/v1/signos-vitales', 'token-admin-norte', Escenario::H_ORIENTE, null],                             // tenant distinto
    ['POST', '/api/v1/autorizaciones/decisiones', 'token-ana', Escenario::H_ORIENTE, ['permiso' => 'roles.asignar', 'recurso' => 'sqa']],
];
$obligatorios = ['permiso', 'recurso', 'motivo', 'ip'];
$completos = 0;
$incompletos = [];
foreach ($intentos as [$met, $rt, $tok, $ten, $cuerpo]) {
    $r = $e->kernel->manejar(Escenario::http($met, $rt, $tok, $ten, $cuerpo));
    $ref = $r->cuerpo['correlation_id'] ?? null;
    $ev = is_string($ref) ? $e->auditoria->buscarPorCorrelacion($ref) : null;
    $ok = $ev !== null && $ev->tipo === 'ACCESO_DENEGADO' && $ev->actorId !== '' && $ev->hospitalId !== ''
        && array_diff($obligatorios, array_keys(array_filter($ev->datos, static fn ($v): bool => $v !== '' && $v !== null))) === [];
    $ok ? $completos++ : $incompletos[] = "$met $rt";
}
$antes = count($e->auditoria->eventos());
foreach (['token-ana', 'token-marta', 'token-admin-oriente'] as $tok) {
    $e->kernel->manejar(Escenario::http('POST', '/api/v1/autorizaciones/permisos-efectivos', $tok, Escenario::H_ORIENTE, ['permisos' => ['roles.asignar', 'prescripciones.crear', 'auditoria.consultar']]));
}
$ruido = count($e->auditoria->eventos()) - $antes;
$registrar('M4', 'Trazabilidad de denegaciones', $pct($completos, count($intentos)) . " % ($completos/" . count($intentos) . " intentos) · ruido: $ruido",
    '100 % con evento completo y 0 eventos por consultas pasivas', $incompletos === [] && $ruido === 0 ? 'VERDE' : 'ROJO',
    $incompletos === [] ? "8 campos presentes en cada evento; $ruido eventos tras 3 consultas de permisos efectivos" : 'incompletos: ' . implode(', ', $incompletos));

// ---------------------------------------------------------------------------------------------
// M5 · Confiabilidad fail-closed: con fallas inyectadas, NUNCA se permite ni se cambia una membresía.
$casos = [];
$casos['lectura de membresías caída → decisión'] = (function (): bool {
    $e = new Escenario();
    $e->membresias->fallarLectura = true;
    return !$e->autorizar->ejecutar(Escenario::comandoAutorizar('admin-01', 'roles.asignar'))->permitido;
})();
$casos['lectura caída → permisos efectivos'] = (function (): bool {
    $e = new Escenario();
    $e->membresias->fallarLectura = true;
    return array_filter($e->caso('permisosEfectivos')->ejecutar('admin-01', Escenario::H_ORIENTE, Escenario::H_ORIENTE, ['roles.asignar'])) === [];
})();
$casos['bitácora principal caída → sigue denegando'] = (function (): bool {
    $e = new Escenario();
    $e->auditoria->fallar = true;
    $r = $e->autorizar->ejecutar(Escenario::comandoAutorizar('ana', 'prescripciones.crear'));
    return !$r->permitido && $e->respaldo->eventos() !== [];
})();
$casos['política sin reglas'] = (function (): bool {
    $s = new SolicitudAutorizacion('ana', 'hosp-oriente', 'hosp-oriente', 'expediente.leer', 'sqa', '', new DateTimeImmutable(Escenario::AHORA));
    return (new PoliticaAutorizacion([]))->evaluar(new ContextoAutorizacion($s, [])) !== null;
})();
$casos['regla que lanza excepción'] = (function (): bool {
    $rota = new class () implements ReglaAutorizacion {
        public function evaluar(ContextoAutorizacion $contexto): ?string
        {
            throw new RuntimeException('falla inyectada');
        }
    };
    $m = new Membresia('ana', 'hosp-oriente', 'enfermeria', 'active', new DateTimeImmutable('2026-01-01'), null);
    $s = new SolicitudAutorizacion('ana', 'hosp-oriente', 'hosp-oriente', 'expediente.leer', 'sqa', '', new DateTimeImmutable(Escenario::AHORA));
    return (new PoliticaAutorizacion([$rota]))->evaluar(new ContextoAutorizacion($s, [$m])) !== null;
})();
$casos['escritura caída al asignar → sin membresía'] = (function (): bool {
    $e = new Escenario();
    $e->membresias->fallarEscritura = true;
    $r = $e->asignarRol->ejecutar(Escenario::comandoAsignar('admin-01', 'nuevo-01', 'medico'));
    return $r->codigoHttp >= 500 && !$e->membresias->existeActiva('nuevo-01', Escenario::H_ORIENTE, 'medico');
})();
$casos['bitácora caída al asignar → sin membresía (R-07)'] = (function (): bool {
    $e = new Escenario();
    $e->auditoria->fallar = true;
    $r = $e->asignarRol->ejecutar(Escenario::comandoAsignar('admin-01', 'nuevo-01', 'medico'));
    return $r->codigoHttp >= 500 && !$e->membresias->existeActiva('nuevo-01', Escenario::H_ORIENTE, 'medico');
})();
$fallidos = array_keys(array_filter($casos, static fn (bool $ok): bool => !$ok));
$registrar('M5', 'Confiabilidad fail-closed ante fallas inyectadas', $pct(count($casos) - count($fallidos), count($casos)) . ' % (' . (count($casos) - count($fallidos)) . '/' . count($casos) . ')',
    '100 %', $fallidos === [] ? 'VERDE' : 'ROJO', $fallidos === [] ? 'ningún caso permite ni deja cambios a medias' : 'fallan: ' . implode('; ', $fallidos));

// ---------------------------------------------------------------------------------------------
// M6 · Hallazgos de revisión Crítico/Mayor sin verificar (registro de la S13).
$csv = array_map(static fn (string $l): array => str_getcsv($l, ',', '"', ''), file("$rbac/semana-13/plantillas/registro-hallazgos.csv", FILE_IGNORE_NEW_LINES) ?: []);
$cab = array_map(static fn ($c) => trim((string) $c, "\xEF\xBB\xBF"), array_shift($csv) ?? []);
$abiertos = ['Crítico' => [], 'Mayor' => [], 'Menor' => []];
foreach ($csv as $fila) {
    $h = array_combine($cab, array_pad($fila, count($cab), ''));
    if (isset($abiertos[$h['severidad']]) && $h['estado'] !== 'Verificado' && $h['estado'] !== 'Rechazado') {
        $abiertos[$h['severidad']][] = $h['id'];
    }
}
$cm = count($abiertos['Crítico']) + count($abiertos['Mayor']);
$registrar('M6', 'Hallazgos de revisión Crítico/Mayor sin verificar', "$cm (Crítico " . count($abiertos['Crítico']) . ' · Mayor ' . count($abiertos['Mayor']) . ') · Menor ' . count($abiertos['Menor']),
    '0 Crítico; Mayor = 0 para liberar', count($abiertos['Crítico']) > 0 ? 'ROJO' : (count($abiertos['Mayor']) > 0 ? 'ROJO' : 'VERDE'),
    $cm === 0 ? 'sin pendientes' : 'abiertos: ' . implode(', ', array_merge($abiertos['Crítico'], $abiertos['Mayor'])));

// ---------------------------------------------------------------------------------------------
// M7 · Tiempo de la decisión (p95, en proceso, adaptadores en memoria).
$e = new Escenario();
$muestras = [];
$permisos = ['signos_vitales.registrar', 'prescripciones.crear', 'expediente.leer'];
for ($i = 0; $i < 3000; $i++) {
    $t = hrtime(true);
    $e->autorizar->ejecutar(Escenario::comandoAutorizar($i % 2 ? 'ana' : 'marta', $permisos[$i % 3]));
    $muestras[] = (hrtime(true) - $t) / 1e6;
}
sort($muestras);
$p95 = $muestras[(int) floor(0.95 * count($muestras)) - 1];
$registrar('M7', 'Tiempo de decisión p95 (RNF-09)', sprintf('%.3f ms (n = 3000, en memoria)', $p95), '< 50 ms p95 con PostgreSQL',
    $p95 < 50 ? 'ÁMBAR' : 'ROJO', 'medido sin base de datos: VERDE solo cuando se repita con PDO + PostgreSQL (S15)');

// ---------------------------------------------------------------------------------------------
echo 'Plan SQA RBAC · medición (PHP ' . PHP_VERSION . ')' . PHP_EOL . str_repeat('-', 110) . PHP_EOL;
foreach ($metricas as $x) {
    printf("%-6s %-3s %-48s %s\n       umbral: %s\n       %s\n", $x['estado'], $x['id'], $x['nombre'], $x['valor'], $x['umbral'], $x['detalle']);
}
$rojos = count(array_filter($metricas, static fn ($x): bool => $x['estado'] === 'ROJO'));
$ambar = count(array_filter($metricas, static fn ($x): bool => $x['estado'] === 'ÁMBAR'));
echo str_repeat('-', 110) . PHP_EOL . "VERDE: " . (count($metricas) - $rojos - $ambar) . "  ÁMBAR: $ambar  ROJO: $rojos" . PHP_EOL;
exit($rojos > 0 ? 1 : 0);

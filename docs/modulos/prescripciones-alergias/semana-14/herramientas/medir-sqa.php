<?php

declare(strict_types=1);

/**
 * Semana 14 · Mide las 7 métricas del plan SQA de Prescripciones sobre el Micro-HIS REAL y las compara con su umbral.
 *
 * Uso: php herramientas/medir-sqa.php [--sin-mutaciones]   (desde docs/modulos/prescripciones-alergias/semana-14)
 *   - Con PRESCRIPCIONES_DB_DSN definida, M1 incluye las pruebas de PostgreSQL y M2 las mutaciones que solo detecta la base.
 *   - SQA_MICROHIS mide otra copia (p. ej. con un defecto inyectado, para probar que las métricas lo detectan).
 * Salida: VERDE / ÁMBAR / ROJO por métrica. Código 0 si ninguna está en ROJO; 1 si alguna lo está.
 */
$micro = getenv('SQA_MICROHIS') ?: dirname(__DIR__, 2) . '/micro-his-prescripciones';
$modulo = dirname(__DIR__, 2);
require $micro . '/bootstrap/contenedor.php';
foreach (glob($micro . '/tests/Dobles/*.php') as $archivo) {
    require_once $archivo;
}

use Prescripciones\Dominio\Modelo\Alergia;
use Prescripciones\Persistencia\FabricaAdaptadores;
use Prescripciones\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Prescripciones\Presentacion\Http\Solicitud;

$metricas = [];
$anotar = static function (string $id, string $nombre, string $valor, string $umbral, string $estado, string $detalle) use (&$metricas): void {
    $metricas[] = compact('id', 'nombre', 'valor', 'umbral', 'estado', 'detalle');
};
$config = require "$micro/config/config.php";
$datos = require "$micro/database/datos-demo.php";
/** Un sistema nuevo con los adaptadores InMemory de producción y un Expediente que se puede «cortar». */
$nuevo = static function () use ($config, $datos): object {
    $p = FabricaAdaptadores::memoria($datos, $config);
    $memoria = $p['alergias'];
    $p['alergias'] = new FuenteConmutable($memoria);

    return (object) ['kernel' => construirKernel(construirCasosDeUso($p), new AutenticadorTokenDemo($config['tokens_demo'])), 'p' => $p, 'memoria' => $memoria];
};
$pedir = static function (object $s, string $metodo, string $ruta, string $token = 'token-dr-lopez', ?array $cuerpo = null, ?string $clave = null, ?string $tenant = 'hosp-norte') {
    $cab = ($token !== '' ? ['authorization' => "Bearer $token"] : []) + ($tenant !== null ? ['x-tenant-id' => $tenant] : []) + ($clave !== null ? ['idempotency-key' => $clave] : []);

    return $s->kernel->manejar(new Solicitud($metodo, "/api/v1/prescripciones$ruta", $cab, $cuerpo === null ? '' : (string) json_encode($cuerpo)));
};
$item = static fn (string $m, int $d = 500, int $f = 8): array => ['medicamento' => $m, 'dosis' => $d, 'unidad' => 'mg', 'frecuencia_horas' => $f, 'via' => 'oral', 'dias' => 5];
$just = 'Sin alternativa: cultivo sensible solo a cefalosporinas';

// ---------------------------------------------------------------------------------------------
// M1 · Calidad: pruebas automáticas en verde.
$salida = (string) shell_exec('php ' . escapeshellarg("$micro/tests/ejecutar.php") . ' 2>&1');
preg_match('/Total:\s*(\d+)\s+Pasan:\s*(\d+)\s+Fallan:\s*(\d+)\s+Omitidas:\s*(\d+)/', $salida, $m);
[$total, $pasan, $fallan, $omitidas] = array_map('intval', array_slice($m, 1, 4) + [0, 0, 1, 0]);
$anotar('M1', 'Pruebas automáticas en verde (calidad)', "$pasan/" . ($total - $omitidas) . " · $omitidas omitidas", '0 fallan y 0 omitidas en CI (PostgreSQL)',
    $fallan > 0 ? 'ROJO' : ($omitidas > 0 ? 'ÁMBAR' : 'VERDE'), $omitidas > 0 ? "las $omitidas omitidas requieren PostgreSQL (PRESCRIPCIONES_DB_DSN)" : 'incluye integración con PostgreSQL');

// ---------------------------------------------------------------------------------------------
// M2 · Calidad de las pruebas: mutaciones detectadas.
if (in_array('--sin-mutaciones', $argv, true)) {
    $anotar('M2', 'Defectos inyectados detectados (mutation score)', 'no medido', '100 %', 'ÁMBAR', 'se pidió --sin-mutaciones');
} else {
    $mut = (string) shell_exec('php ' . escapeshellarg("$micro/tests/mutaciones.php") . ' 2>&1');
    preg_match('/Mutaciones:\s*(\d+)\s+Detectadas:\s*(\d+)\s+Sobreviven:\s*(\d+)/', $mut, $mm);
    [$nMut, $detectadas] = [(int) ($mm[1] ?? 0), (int) ($mm[2] ?? 0)];
    $score = $nMut === 0 ? 0.0 : round(100 * $detectadas / $nMut, 1);
    $anotar('M2', 'Defectos inyectados detectados (mutation score)', "$score % ($detectadas/$nMut)", '100 %', $score === 100.0 ? 'VERDE' : 'ROJO',
        getenv('PRESCRIPCIONES_DB_DSN') ? 'con PostgreSQL' : 'sin PostgreSQL: las mutaciones del esquema solo las detecta la base');
}

// ---------------------------------------------------------------------------------------------
// M3 · Seguridad clínica: sensibilidad de la verificación sobre un CORPUS de alergias reales escritas de distintas formas.
// Cada caso: alergia activa en P-003 (que no tiene ninguna), medicamento y los resultados aceptables de la previa.
$corpus = [
    ['código J01CA04, grave', ['J01CA04', 'Amoxicilina', 'allergy', 'severe'], 'AMOX500', ['BLOQUEO']],
    ['nombre «Amoxicilina», grave', [null, 'Amoxicilina', 'allergy', 'severe'], 'AMOX500', ['BLOQUEO']],
    ['nombre «  AMOXICILINA », moderada', [null, '  AMOXICILINA ', 'allergy', 'moderate'], 'AMOX500', ['BLOQUEO']],
    ['nombre «amoxicilina», leve', [null, 'amoxicilina', 'allergy', 'mild'], 'AMOX500', ['BLOQUEO']],
    ['nombre «Ibuprofeno», grave', [null, 'Ibuprofeno', 'allergy', 'severe'], 'IBU400', ['BLOQUEO']],
    ['cruzada: código J01CA04 moderada + Cefalexina', ['J01CA04', 'Amoxicilina', 'allergy', 'moderate'], 'CEFA500', ['ADVERTENCIA']],
    ['cruzada: «Penicilina» moderada + Cefalexina', [null, 'Penicilina', 'allergy', 'moderate'], 'CEFA500', ['ADVERTENCIA']],
    ['cruzada mortal: AAS + Ibuprofeno', ['N02BA01', 'Ácido acetilsalicílico', 'allergy', 'life_threatening'], 'IBU400', ['BLOQUEO']],
    ['cruzada desconocida: «penicilina» + Amoxicilina', [null, 'penicilina', 'unknown', 'unknown'], 'AMOX500', ['BLOQUEO']],
    ['sal: «Amoxicilina trihidrato», grave', [null, 'Amoxicilina trihidrato', 'allergy', 'severe'], 'AMOX500', ['BLOQUEO']],
    ['sal: «Amoxicilina (sal sódica)», grave', [null, 'Amoxicilina (sal sódica)', 'allergy', 'severe'], 'AMOX500', ['BLOQUEO']],
    ['variante: «Penicilina G», grave + Amoxicilina', [null, 'Penicilina G', 'allergy', 'severe'], 'AMOX500', ['ADVERTENCIA', 'BLOQUEO']],
];
$negativos = [
    ['resuelta: J01CA04 resuelta + Amoxicilina', ['J01CA04', 'Amoxicilina', 'allergy', 'severe', 'resolved'], 'AMOX500', ['SIN_HALLAZGOS']],
    ['otro grupo: «Penicilina» + Paracetamol', [null, 'Penicilina', 'allergy', 'severe'], 'PARA500', ['SIN_HALLAZGOS']],
];
$correr = static function (array $casos) use ($nuevo, $pedir, $item): array {
    $fallos = [];
    foreach ($casos as [$nombre, $a, $med, $aceptables]) {
        $s = $nuevo();
        $s->memoria->agregar('P-003', new Alergia($a[0], $a[1], $a[2], $a[3], $a[4] ?? 'active', null));
        $codigo = $pedir($s, 'POST', '/verificaciones', 'token-dr-lopez', ['paciente' => 'P-003', 'items' => [$item($med)]])->cuerpo['codigo'] ?? 'ERROR';
        if (!in_array($codigo, $aceptables, true)) {
            $fallos[] = "$nombre → $codigo";
        }
    }

    return $fallos;
};
$fnSens = $correr($corpus);
$fnEsp = $correr($negativos);
$sens = count($corpus) - count($fnSens);
$anotar('M3', 'Sensibilidad de la verificación de alergias en el corpus (seguridad clínica)', "$sens/" . count($corpus) . ' detectadas · especificidad ' . (count($negativos) - count($fnEsp)) . '/' . count($negativos),
    '100 % de sensibilidad (0 falsos negativos)', $fnSens === [] ? ($fnEsp === [] ? 'VERDE' : 'ÁMBAR') : 'ROJO',
    $fnSens === [] && $fnEsp === [] ? 'corpus de ' . (count($corpus) + count($negativos)) . ' casos: código, nombre, mayúsculas, cruzadas, sales y variantes'
        : 'falsos negativos: ' . implode(' · ', $fnSens) . ($fnEsp ? ' · falsos positivos: ' . implode(' · ', $fnEsp) : ''));

// ---------------------------------------------------------------------------------------------
// M4 · Seguridad: intentos de acceso indebido bloqueados (permisos, tenant, identidad, clave ajena).
$s = $nuevo();
$id = $pedir($s, 'POST', '', 'token-dr-lopez', ['paciente' => 'P-002', 'items' => [$item('CEFA500')], 'justificacion' => $just], 'sqa-lopez')->cuerpo['id'] ?? 0;
$sondas = [
    ['farmacia verifica', fn () => $pedir($s, 'POST', '/verificaciones', 'token-farmacia-norte', ['paciente' => 'P-005', 'items' => [$item('PARA500')]]), [403]],
    ['farmacia emite', fn () => $pedir($s, 'POST', '', 'token-farmacia-norte', ['paciente' => 'P-005', 'items' => [$item('PARA500')]], 'sqa-f1'), [403]],
    ['farmacia anula', fn () => $pedir($s, 'POST', "/$id/anulacion", 'token-farmacia-norte', ['motivo' => 'Motivo suficientemente largo']), [403]],
    ['médico del Sur consulta una del Norte', fn () => $pedir($s, 'GET', "/$id", 'token-dr-sur', null, null, 'hosp-sur'), [404]],
    ['médico del Sur anula una del Norte', fn () => $pedir($s, 'POST', "/$id/anulacion", 'token-dr-sur', ['motivo' => 'Motivo suficientemente largo'], null, 'hosp-sur'), [404]],
    ['token del Norte con X-Tenant-ID del Sur', fn () => $pedir($s, 'GET', "/$id", 'token-dr-lopez', null, null, 'hosp-sur'), [403]],
    ['paciente de otro hospital', fn () => $pedir($s, 'POST', '/verificaciones', 'token-dr-lopez', ['paciente' => 'P-900', 'items' => [$item('PARA500')]]), [404]],
    ['sin override emite una advertencia justificada', fn () => $pedir($s, 'POST', '', 'token-dr-ruiz', ['paciente' => 'P-002', 'items' => [$item('CEFA500')], 'justificacion' => $just], 'sqa-r1'), [409]],
    ['otro médico reusa la clave de dr-lopez', fn () => $pedir($s, 'POST', '', 'token-dr-ruiz', ['paciente' => 'P-002', 'items' => [$item('CEFA500')], 'justificacion' => $just], 'sqa-lopez'), [409]],
    ['borrar una prescripción', fn () => $pedir($s, 'DELETE', "/$id"), [405]],
    ['modificar una prescripción', fn () => $pedir($s, 'PUT', "/$id", 'token-dr-lopez', ['paciente' => 'P-001']), [405]],
    ['sin token', fn () => $pedir($s, 'GET', "/$id", ''), [401]],
];
$fugas = [];
foreach ($sondas as [$nombre, $sonda, $esperados]) {
    $estado = $sonda()->estado;
    if (!in_array($estado, $esperados, true)) {
        $fugas[] = "$nombre → $estado";
    }
}
$anotar('M4', 'Intentos de acceso indebido bloqueados (seguridad)', (count($sondas) - count($fugas)) . '/' . count($sondas), '100 %',
    $fugas === [] ? 'VERDE' : 'ROJO', $fugas === [] ? count($sondas) . ' sondas: permisos, tenant, override, clave ajena, DELETE/PUT y sin token' : 'NO bloqueadas: ' . implode(' · ', $fugas));

// ---------------------------------------------------------------------------------------------
// M5 · Confiabilidad: bloqueo seguro con el Expediente caído y reintentos que no duplican.
$s = $nuevo();
$emitidaAntes = $pedir($s, 'POST', '', 'token-dr-lopez', ['paciente' => 'P-005', 'items' => [$item('PARA500')]], 'sqa-c0')->cuerpo['id'] ?? 0;
$s->p['alergias']->caida = true;
$previa = $pedir($s, 'POST', '/verificaciones', 'token-dr-lopez', ['paciente' => 'P-005', 'items' => [$item('IBU400', 400)]]);
$emision = $pedir($s, 'POST', '', 'token-dr-lopez', ['paciente' => 'P-005', 'items' => [$item('IBU400', 400)]], 'sqa-c1');
$trasCaida = count($s->p['repositorio']->todas());
$anular = $pedir($s, 'POST', "/$emitidaAntes/anulacion", 'token-dr-lopez', ['motivo' => 'Pauta duplicada con la de urgencias']);
$s->p['alergias']->caida = false;
$antesReintentos = count($s->p['repositorio']->todas());
$codigos = [];
for ($i = 0; $i < 50; $i++) {
    $codigos[] = $pedir($s, 'POST', '', 'token-dr-lopez', ['paciente' => 'P-005', 'items' => [$item('IBU400', 400)]], 'sqa-c2')->estado;
}
$nuevas = count($s->p['repositorio']->todas()) - $antesReintentos;
$conf = [
    ['previa con el Expediente caído → «no disponible» (nunca «sin hallazgos»)', ($previa->cuerpo['codigo'] ?? '') === 'VERIFICACION_NO_DISPONIBLE'],
    ['emisión con el Expediente caído → 503 y nada emitido', $emision->estado === 503 && $trasCaida === 1],   // solo la emitida antes del corte
    ['anular con el Expediente caído → 200 (bloqueo seguro asimétrico)', $anular->estado === 200],
    ['50 envíos con la misma clave → 1 prescripción (201 + 49 × 200)', $nuevas === 1 && $codigos[0] === 201 && count(array_filter($codigos, static fn (int $c): bool => $c === 200)) === 49],
];
$malas = array_column(array_filter($conf, static fn (array $c): bool => !$c[1]), 0);
$anotar('M5', 'Bloqueo seguro e idempotencia (confiabilidad)', (count($conf) - count($malas)) . '/' . count($conf), '100 %',
    $malas === [] ? 'VERDE' : 'ROJO', $malas === [] ? implode(' · ', array_column($conf, 0)) : 'fallan: ' . implode(' · ', $malas));

// ---------------------------------------------------------------------------------------------
// M6 · Trazabilidad: contrato = código y cada código de error del contrato afirmado por alguna prueba.
$guardas = ['API-01', 'API-02', 'API-03', 'JUS-04'];
$rojas = array_values(array_filter($guardas, static fn (string $g): bool => !preg_match('/\[PASA \]\s+' . $g . '\b/', $salida)));
$yaml = (string) file_get_contents("$micro/api/openapi.yaml");
preg_match('/\n        error:\n          type: string\n          enum: \[([A-Z_,\s]+)\]/', $yaml, $e);
$codigosError = array_values(array_filter(array_map('trim', explode(',', $e[1] ?? ''))));
$pruebas = implode("\n", array_map('file_get_contents', glob("$micro/tests/*/*.php")));
$sinPrueba = array_values(array_filter($codigosError, static fn (string $c): bool => !str_contains($pruebas, "'$c'")));
$anotar('M6', 'Contrato = código y códigos de error con prueba (trazabilidad)', (count($guardas) - count($rojas)) . '/' . count($guardas) . ' guardas · '
    . (count($codigosError) - count($sinPrueba)) . '/' . count($codigosError) . ' códigos', '100 % y 100 %',
    $rojas === [] && $sinPrueba === [] ? 'VERDE' : ($rojas !== [] ? 'ROJO' : 'ÁMBAR'),
    ($rojas ? 'guardas en rojo: ' . implode(', ', $rojas) . ' · ' : '') . ($sinPrueba ? 'códigos del contrato que ninguna prueba afirma: ' . implode(', ', $sinPrueba) : 'todos los códigos del enum de errores aparecen en una prueba'));

// ---------------------------------------------------------------------------------------------
// M7 · Riesgo: hallazgos de la revisión técnica (semana 13) abiertos, por severidad.
$csv = array_map(static fn (string $l): array => str_getcsv($l, ',', '"', ''), file("$modulo/semana-13/plantillas/registro-hallazgos.csv", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
$cab = array_shift($csv);
$abiertos = ['Crítico' => 0, 'Mayor' => 0, 'Menor' => 0, 'Observación' => 0];
foreach ($csv as $fila) {
    $h = array_combine($cab, $fila);
    if (!in_array($h['estado'], ['Cerrado', 'Rechazado', 'Aceptado (riesgo)'], true)) {
        $abiertos[$h['severidad']]++;
    }
}
$anotar('M7', 'Hallazgos de la revisión abiertos (riesgo)', "{$abiertos['Crítico']} críticos · {$abiertos['Mayor']} mayores · {$abiertos['Menor']} menores · {$abiertos['Observación']} obs.",
    '0 críticos; mayores con plazo ≤ 7 días', $abiertos['Crítico'] > 0 ? 'ROJO' : ($abiertos['Mayor'] > 0 ? 'ÁMBAR' : 'VERDE'), 'fuente: semana-13/plantillas/registro-hallazgos.csv');

// ---------------------------------------------------------------------------------------------
echo 'Plan SQA Prescripciones · medición ' . date('Y-m-d H:i') . ' · micro-his: ' . basename($micro) . PHP_EOL . str_repeat('-', 110) . PHP_EOL;
$rojos = 0;
foreach ($metricas as $x) {
    $rojos += $x['estado'] === 'ROJO' ? 1 : 0;
    printf("%-6s %-3s %s\n       valor: %s · umbral: %s\n       %s\n", $x['estado'], $x['id'], $x['nombre'], $x['valor'], $x['umbral'], $x['detalle']);
}
echo str_repeat('-', 110) . PHP_EOL . "En ROJO: $rojos" . PHP_EOL;
exit($rojos > 0 ? 1 : 0);

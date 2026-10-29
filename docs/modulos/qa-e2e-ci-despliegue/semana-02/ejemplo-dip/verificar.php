<?php

declare(strict_types=1);

/*
 * Semana 2 · Criterios de aceptación ejecutables del diseño DESPUÉS de aplicar DIP.
 * Ninguno usa red, runner ni base: la regla se prueba con adaptadores en memoria y un reloj fijo, y la portabilidad
 * con dos formatos reales de reporte (JUnit y JSON) leídos de datos/.
 *   php verificar.php [directorio-del-diseño]      (por defecto despues/; mutaciones.php pasa una copia mutada)
 * Código de salida: cantidad de criterios que fallan.
 */

$base = $argv[1] ?? __DIR__ . '/despues';
require $base . '/autoload.php';

use E2e\Adaptadores\AlmacenEnDisco;
use E2e\Adaptadores\AlmacenEnMemoria;
use E2e\Adaptadores\EvaluacionesEnMemoria;
use E2e\Adaptadores\FuenteEnMemoria;
use E2e\Adaptadores\RelojFijo;
use E2e\Adaptadores\ReporteJson;
use E2e\Adaptadores\ReporteJUnit;
use E2e\Aplicacion\EvaluarGate;
use E2e\Dominio\Decision;
use E2e\Dominio\Ejecucion;
use E2e\Dominio\PoliticaGate;
use E2e\Dominio\ReglaRechazada;
use E2e\Dominio\Resultado;
use E2e\Dominio\ResultadoEscenario;

$fallas = 0;
function criterio(string $id, string $rf, string $texto, callable $prueba): void
{
    global $fallas;
    try {
        $prueba();
        printf("[CUMPLE] %-6s %-14s %s\n", $id, $rf, $texto);
    } catch (Throwable $e) {
        $fallas++;
        printf("[FALLA ] %-6s %-14s %s\n         -> %s\n", $id, $rf, $texto, $e->getMessage());
    }
}
function igual(mixed $esperado, mixed $real, string $que): void
{
    if ($esperado !== $real) {
        throw new RuntimeException("$que: esperado " . json_encode($esperado, JSON_UNESCAPED_UNICODE) . ', obtenido ' . json_encode($real, JSON_UNESCAPED_UNICODE));
    }
}

const DIGEST = 'sha256:50333226f021db85e436d040e924d56f0034fb247c7a05d0a91acb992602d325';
const EVIDENCIA = 'aaaa000000000000000000000000000000000000000000000000000000000001';
$politica = new PoliticaGate('gate-1', 0.75, 1, 24);
$terminada = new DateTimeImmutable('2026-10-29T14:00:00Z');
$r = static fn (string $nombre, bool $critico, Resultado $res, int $intentos = 1, array $ev = []): ResultadoEscenario
    => new ResultadoEscenario($nombre, $critico, $res, $intentos, $ev);
/** Una ejecución sana: 2 críticos y 4 no críticos que pasan. Cada criterio cambia UNA cosa. */
$sana = static fn (array $cambios = [], string $digest = DIGEST, bool $completada = true): Ejecucion => new Ejecucion('run-1', $digest, $completada, $terminada,
    array_values(array_merge(['c1' => $r('Admitir paciente', true, Resultado::PASA), 'c2' => $r('Prescribir', true, Resultado::PASA),
        'n1' => $r('Laboratorio', false, Resultado::PASA), 'n2' => $r('Epicrisis', false, Resultado::PASA),
        'n3' => $r('Buscar paciente', false, Resultado::PASA), 'n4' => $r('Agendar', false, Resultado::PASA)], $cambios)));
$evaluar = static function (Ejecucion $e, string $digest = DIGEST, string $hora = '2026-10-29T20:00:00Z', ?EvaluacionesEnMemoria $repo = null) use ($politica): Decision {
    $uc = new EvaluarGate(new FuenteEnMemoria(['run-1' => $e]), new AlmacenEnMemoria([EVIDENCIA]), new RelojFijo(new DateTimeImmutable($hora)),
        $repo ?? new EvaluacionesEnMemoria());

    return $uc->ejecutar('run-1', $digest, $politica);
};

criterio('CA-01', 'RF-09', 'Todo pasa sobre el digest, a tiempo y con evidencia → APROBADO sin causas', function () use ($evaluar, $sana): void {
    $d = $evaluar($sana());
    igual([Decision::APROBADO, []], [$d->valor, $d->causas], 'decisión');
});
criterio('CA-02', 'RF-09 · R-09a', 'Un crítico que FALLA (con evidencia) → RECHAZADO y lo nombra', function () use ($evaluar, $sana, $r): void {
    $d = $evaluar($sana(['c2' => $r('Prescribir', true, Resultado::FALLA, 1, [EVIDENCIA])]));
    igual([Decision::RECHAZADO, false, ['críticos que no pasan: Prescribir']], [$d->valor, $d->reglas['a_criticos'], $d->causas], 'decisión');
});
criterio('CA-03', 'RF-06 · R-06', 'Un crítico INESTABLE (pasó al segundo intento) no cuenta como PASA → RECHAZADO', function () use ($evaluar, $sana, $r): void {
    $d = $evaluar($sana(['c1' => $r('Admitir paciente', true, Resultado::INESTABLE, 2, [EVIDENCIA])]));
    igual([Decision::RECHAZADO, false], [$d->valor, $d->reglas['a_criticos']], 'decisión');
});
criterio('CA-04', 'RF-09 · R-09a', 'Un crítico OMITIDO tampoco cuenta como PASA', function () use ($evaluar, $sana, $r): void {
    igual(false, $evaluar($sana(['c1' => $r('Admitir paciente', true, Resultado::OMITIDO, 0)]))->reglas['a_criticos'], 'regla a');
});
criterio('CA-05', 'RF-05 · R-05', 'Una FALLA sin evidencia, o con una huella que el almacén no confirma → regla d', function () use ($evaluar, $sana, $r): void {
    $sinNada = $evaluar($sana(['n2' => $r('Epicrisis', false, Resultado::FALLA)]));
    $noConfirmada = $evaluar($sana(['n2' => $r('Epicrisis', false, Resultado::FALLA, 1, [EVIDENCIA, str_repeat('b', 64)])]));
    igual([false, false], [$sinNada->reglas['d_evidencia'], $noConfirmada->reglas['d_evidencia']], 'regla d');
});
criterio('CA-06', 'RF-09 · R-09b', 'Límite de la tasa de no críticos: 3 de 4 (75 %) con umbral 75 % aprueba; 2 de 4 no', function () use ($evaluar, $sana, $r): void {
    $f = static fn (string $n): ResultadoEscenario => $r($n, false, Resultado::FALLA, 1, [EVIDENCIA]);
    igual([true, false], [$evaluar($sana(['n4' => $f('Agendar')]))->reglas['b_tasa'], $evaluar($sana(['n3' => $f('Buscar'), 'n4' => $f('Agendar')]))->reglas['b_tasa']], 'regla b');
});
criterio('CA-07', 'RF-09 · R-09b', 'Omitir no mejora la tasa: dos no críticos OMITIDOS cuentan igual que dos que FALLAN', function () use ($evaluar, $sana, $r): void {
    $omitidos = $evaluar($sana(['n3' => $r('Buscar', false, Resultado::OMITIDO, 0), 'n4' => $r('Agendar', false, Resultado::OMITIDO, 0)]));
    $fallan = $evaluar($sana(['n3' => $r('Buscar', false, Resultado::FALLA, 1, [EVIDENCIA]), 'n4' => $r('Agendar', false, Resultado::FALLA, 1, [EVIDENCIA])]));
    igual([false, false], [$omitidos->reglas['b_tasa'], $fallan->reglas['b_tasa']], '2 de 4 = 50 % < 75 % en los dos casos');
});
criterio('CA-08', 'RF-09 · R-09c', 'Inestables: 1 (el máximo) aprueba la regla c; 2 no', function () use ($evaluar, $sana, $r): void {
    $i = static fn (string $n): ResultadoEscenario => $r($n, false, Resultado::INESTABLE, 2, [EVIDENCIA]);
    igual([true, false], [$evaluar($sana(['n1' => $i('Laboratorio')]))->reglas['c_inestables'],
        $evaluar($sana(['n1' => $i('Laboratorio'), 'n2' => $i('Epicrisis')]))->reglas['c_inestables']], 'regla c');
});
criterio('CA-09', 'RF-09 · R-09e', 'Antigüedad con reloj fijo: 24 h exactas aprueba; 24 h y 1 s no', function () use ($evaluar, $sana): void {
    igual([true, false], [$evaluar($sana(), hora: '2026-10-30T14:00:00Z')->reglas['e_antiguedad'],
        $evaluar($sana(), hora: '2026-10-30T14:00:01Z')->reglas['e_antiguedad']], 'regla e');
});
criterio('CA-10', 'RF-04 · R-03', 'La ejecución probó otro digest → RECHAZADO aunque todo pase', function () use ($evaluar, $sana): void {
    $d = $evaluar($sana(digest: 'sha256:' . str_repeat('0', 64)));
    igual([Decision::RECHAZADO, ['la ejecución probó otro digest']], [$d->valor, $d->causas], 'decisión');
});
criterio('CA-11', 'RF-07', 'Una ejecución no completada no se evalúa: EJECUCION_NO_COMPLETADA', function () use ($evaluar, $sana): void {
    try {
        $evaluar($sana(completada: false));
        throw new RuntimeException('no se rechazó');
    } catch (ReglaRechazada $e) {
        igual('EJECUCION_NO_COMPLETADA', $e->codigo, 'código');
    }
});
criterio('CA-12', 'RF-10', 'Cada evaluación se agrega: evaluar dos veces deja dos decisiones con la política y el digest', function () use ($evaluar, $sana): void {
    $repo = new EvaluacionesEnMemoria();
    $evaluar($sana(), repo: $repo);
    $evaluar($sana(), repo: $repo);
    igual([2, 'gate-1', DIGEST], [count($repo->deEjecucion('run-1')), $repo->deEjecucion('run-1')[1]->politica, $repo->deEjecucion('run-1')[1]->digest], 'evaluaciones');
});
criterio('CA-13', 'RNF-03', 'Portabilidad: la MISMA ejecución en JUnit y en el JSON de otro proveedor da la MISMA decisión', function () use ($politica): void {
    $datos = __DIR__ . '/datos';
    $decidir = static fn ($fuente): Decision => (new EvaluarGate($fuente, new AlmacenEnDisco("$datos/almacen"),
        new RelojFijo(new DateTimeImmutable('2026-10-29T20:00:00Z')), new EvaluacionesEnMemoria()))->ejecutar('run-101', DIGEST, $politica);
    $junit = $decidir(new ReporteJUnit($datos));
    $json = $decidir(new ReporteJson($datos));
    igual([$junit->valor, $junit->reglas, $junit->causas], [$json->valor, $json->reglas, $json->causas], 'JUnit frente a JSON');
    igual(Decision::RECHAZADO, $junit->valor, 'run-101 tiene la epicrisis en FALLA y un omitido: tasa 2 de 5');
});
criterio('CA-14', 'RNF-04 · R-08', 'Almacén en disco: confirma un artefacto por su SHA-256 y rechaza uno alterado', function (): void {
    $dir = sys_get_temp_dir() . '/e2e-dip-' . getmypid();
    @mkdir($dir);
    $sha = hash('sha256', 'traza');
    file_put_contents("$dir/$sha", 'traza');
    $alm = new AlmacenEnDisco($dir);
    $antes = $alm->confirmada($sha);
    file_put_contents("$dir/$sha", 'traza alterada');
    igual([true, false, false], [$antes, $alm->confirmada($sha), $alm->confirmada('../' . $sha)], 'confirmación');
    array_map('unlink', glob("$dir/*") ?: []);
    rmdir($dir);
});

echo "\nCriterios: 14  Fallan: $fallas\n";
exit($fallas);

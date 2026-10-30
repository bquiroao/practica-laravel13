<?php

declare(strict_types=1);

/*
 * Semana 14 · RNF-08: evaluar el gate de una ejecución de 300 escenarios en menos de 1 s.
 *   php bin/medir-gate.php [escenarios=300] [repeticiones=50]
 * Mide solo la función del dominio (QualityGate::evaluar), que es lo que promete el RNF; sin base ni HTTP.
 */

require dirname(__DIR__) . '/bootstrap/autoload.php';

use E2e\Dominio\Modelo\Ejecucion;
use E2e\Dominio\Modelo\EscenarioE2E;
use E2e\Dominio\Modelo\PoliticaGate;
use E2e\Dominio\Modelo\QualityGate;
use E2e\Dominio\Modelo\ResultadoEscenario;
use E2e\Dominio\Modelo\Version;

$n = (int) ($argv[1] ?? 300);
$repeticiones = (int) ($argv[2] ?? 50);
$hora = new DateTimeImmutable('2026-10-30T12:00:00Z');
$catalogo = [];
for ($i = 1; $i <= $n; $i++) {
    $catalogo[] = new EscenarioE2E('t', sprintf('E2E-%04d', $i), "Escenario $i", 'expediente', $i <= 30, ['RF-01'], 'SINT-A', 'x');
}
$v = Version::registrar('t', '9.9.9', 'c56f169', 'sha256:' . str_repeat('a', 64), ['expediente'], 'ci');
$v->planificar($catalogo, array_map(static fn (EscenarioE2E $e): string => $e->id, $catalogo));
$e = new Ejecucion(1, 't', '9.9.9', 'sha256:' . str_repeat('a', 64), 'staging', $v->plan(), $hora);
foreach ($catalogo as $k => $esc) {
    $e->agregar(ResultadoEscenario::desde($esc->id, $esc->critico, $k % 97 === 0 ? 'FALLA' : 'PASA', 1, [], "k-$k", 'h', $hora));
}
$e->completar($hora);
$politica = new PoliticaGate('gate-2026.1', 0.95, 2, 24);

$tiempos = [];
for ($r = 0; $r < $repeticiones; $r++) {
    $t0 = hrtime(true);
    $d = QualityGate::evaluar($politica, $e, $v, $hora, static fn (): bool => true, 'medicion');
    $tiempos[] = (hrtime(true) - $t0) / 1e6;
}
sort($tiempos);
$p95 = $tiempos[(int) floor(0.95 * (count($tiempos) - 1))];
printf("Escenarios: %d · repeticiones: %d · decisión: %s\n", $n, $repeticiones, $d->valor);
printf("mediana %.2f ms · p95 %.2f ms · máximo %.2f ms · umbral RNF-08: 1000 ms → %s\n", $tiempos[intdiv(count($tiempos), 2)], $p95, end($tiempos), $p95 < 1000 ? 'CUMPLE' : 'NO CUMPLE');

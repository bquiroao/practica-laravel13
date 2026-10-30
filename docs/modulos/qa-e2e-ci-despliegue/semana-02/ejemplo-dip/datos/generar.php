<?php

declare(strict_types=1);

/*
 * Genera los datos del ejemplo: los artefactos (en almacen/, nombrados por su SHA-256) y la MISMA ejecución exportada en
 * los dos formatos (run-101.xml JUnit y run-101.json de otro proveedor). Los pacientes son sintéticos (R-01).
 *   php datos/generar.php
 */

$dir = __DIR__;
@mkdir("$dir/almacen");
$guardar = static function (string $contenido) use ($dir): string {
    $sha = hash('sha256', $contenido);
    file_put_contents("$dir/almacen/$sha", $contenido);

    return $sha;
};
$traza = $guardar("traza playwright · resultados de laboratorio · paciente sintético SINT-0042 · intento 1 falla por tiempo de espera\n");
$captura = $guardar("captura · epicrisis · el botón «Imprimir» no responde · paciente sintético SINT-0017\n");
$log = $guardar("log · epicrisis · 500 en /api/epicrisis/SINT-0017/pdf\n");

// [nombre, crítico, estado (PASA/FALLA/INESTABLE/OMITIDO), intentos, evidencias]
$escenarios = [
    ['Admitir paciente y asignar cama', true, 'PASA', 1, []],
    ['Prescribir con alergia registrada', true, 'PASA', 1, []],
    ['Ingresar resultado de laboratorio', false, 'INESTABLE', 2, [$traza]],
    ['Imprimir epicrisis', false, 'FALLA', 1, [$captura, $log]],
    ['Buscar paciente por documento', false, 'PASA', 1, []],
    ['Agendar cita', false, 'PASA', 1, []],
    ['Exportar reporte operativo', false, 'OMITIDO', 0, []],
];
$digest = 'sha256:' . hash('sha256', 'his-2.4.0+c56f169');
$terminada = '2026-10-29T14:00:00Z';

$xml = new SimpleXMLElement('<testsuites/>');
$suite = $xml->addChild('testsuite');
$suite->addAttribute('name', 'his-e2e');
$suite->addAttribute('timestamp', $terminada);
$props = $suite->addChild('properties');
foreach (['digest' => $digest, 'estado' => 'completada'] as $n => $v) {
    $p = $props->addChild('property');
    $p->addAttribute('name', $n);
    $p->addAttribute('value', $v);
}
$json = ['run' => 'run-101', 'artifact_digest' => $digest, 'state' => 'finished', 'finished_at' => $terminada, 'tests' => []];
foreach ($escenarios as [$nombre, $critico, $estado, $intentos, $evidencias]) {
    $caso = $suite->addChild('testcase');
    $caso->addAttribute('name', $nombre . ($critico ? ' @critico' : ''));
    $caso->addAttribute('classname', 'his.e2e');
    $pp = $caso->addChild('properties');
    foreach ([['intentos', (string) $intentos], ...array_map(static fn (string $e): array => ['evidencia', $e], $evidencias)] as [$n, $v]) {
        $p = $pp->addChild('property');
        $p->addAttribute('name', $n);
        $p->addAttribute('value', $v);
    }
    if ($estado === 'FALLA') {
        $caso->addChild('failure', 'expect(locator).toBeVisible() falló')->addAttribute('message', 'el PDF no se generó');
    }
    if ($estado === 'OMITIDO') {
        $caso->addChild('skipped');
    }
    $json['tests'][] = ['name' => $nombre, 'labels' => $critico ? ['critical', 'e2e'] : ['e2e'],
        'status' => ['PASA' => 'success', 'FALLA' => 'failed', 'INESTABLE' => 'flaky', 'OMITIDO' => 'skipped'][$estado],
        'attempts' => $intentos, 'artifacts' => array_map(static fn (string $e): array => ['sha256' => $e], $evidencias)];
}
$dom = dom_import_simplexml($xml)->ownerDocument;
$dom->formatOutput = true;
file_put_contents("$dir/run-101.xml", $dom->saveXML());
file_put_contents("$dir/run-101.json", json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
echo "ok: 3 artefactos, run-101.xml y run-101.json\n";

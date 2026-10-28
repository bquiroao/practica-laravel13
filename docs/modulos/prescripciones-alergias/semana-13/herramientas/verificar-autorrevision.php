<?php

declare(strict_types=1);

/**
 * Semana 13 · Reproduce los hallazgos de la autorrevisión del autor (AR-xx) contra el kernel REAL del Micro-HIS
 * Prescripciones (adaptadores InMemory de producción y database/datos-demo.php). Cada bloque imprime la evidencia que
 * cita el hallazgo. No modifica nada del Micro-HIS.
 *
 * Uso: php herramientas/verificar-autorrevision.php   (desde docs/modulos/prescripciones-alergias/semana-13)
 */
$micro = dirname(__DIR__, 2) . '/micro-his-prescripciones';
require $micro . '/bootstrap/contenedor.php';

use Prescripciones\Dominio\Modelo\Alergia;
use Prescripciones\Persistencia\FabricaAdaptadores;
use Prescripciones\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Prescripciones\Presentacion\Http\Solicitud;

$config = require "$micro/config/config.php";
$config['persistencia'] = 'memoria';
$config['registro_acceso'] = 'ninguno';
/** Un sistema nuevo: el kernel y sus puertos (para mirar el repositorio, la bandeja de trazas y el Expediente). */
$sistema = static function () use ($config, $micro): array {
    $p = FabricaAdaptadores::memoria(require "$micro/database/datos-demo.php", $config);

    return [construirKernel(construirCasosDeUso($p), new AutenticadorTokenDemo($config['tokens_demo'])), $p];
};
$pedir = static function ($k, string $metodo, string $ruta, string $token, ?string $cuerpo = null, ?string $clave = null) {
    $cab = ['authorization' => "Bearer $token", 'x-tenant-id' => 'hosp-norte'] + ($clave !== null ? ['idempotency-key' => $clave] : []);

    return $k->manejar(new Solicitud($metodo, "/api/v1/prescripciones$ruta", $cab, $cuerpo ?? ''));
};
$item = static fn (string $m, int $d = 500, int $f = 8): array => ['medicamento' => $m, 'dosis' => $d, 'unidad' => 'mg', 'frecuencia_horas' => $f, 'via' => 'oral', 'dias' => 7];
$json = static fn (array $c): string => mb_strimwidth(json_encode($c, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 150, '…');

echo 'AR-01 · El punto de entrada real (crearKernel) acepta siempre los tokens de demostración' . PHP_EOL;
$k = crearKernel($config);
$r = $pedir($k, 'POST', '', 'token-dr-lopez', (string) json_encode(['paciente' => 'P-005', 'items' => [$item('PARA500')]]), 'ar01');
$contenedor = (string) file_get_contents("$micro/bootstrap/contenedor.php");
printf("  crearKernel(config) + «Bearer token-dr-lopez» (texto fijo de config.php) → HTTP %d %s: EMITE una prescripción\n", $r->estado, $r->cuerpo['codigo'] ?? $r->cuerpo['error']);
printf("  tokens de demostración en config.php: %d (uno con override) · crearKernel() elige el autenticador según el entorno: %s\n\n",
    count($config['tokens_demo']), preg_match('/PRESCRIPCIONES_ENTORNO|getenv\([^)]*ENTORNO/', $contenedor) ? 'sí' : 'NO (siempre AutenticadorTokenDemo)');

echo 'AR-02 · La clave de idempotencia es del HOSPITAL, no del prescriptor: otro médico recibe «ya emitida» una prescripción ajena' . PHP_EOL;
[$k, $p] = $sistema();
$cuerpo = (string) json_encode(['paciente' => 'P-002', 'items' => [$item('CEFA500')], 'justificacion' => 'Sin alternativa: cultivo sensible solo a cefalosporinas']);
$a = $pedir($k, 'POST', '', 'token-dr-lopez', $cuerpo, 'clave-compartida');
$b = $pedir($k, 'POST', '', 'token-dr-ruiz', $cuerpo, 'clave-compartida');
$sinClave = $pedir($k, 'POST', '', 'token-dr-ruiz', $cuerpo, 'clave-propia-ruiz');
printf("  dr-lopez emite (con advertencia justificada) con la clave «clave-compartida» → HTTP %d %s #%s\n", $a->estado, $a->cuerpo['codigo'], $a->cuerpo['id']);
printf("  dr-ruiz (SIN permiso override) envía lo mismo con la misma clave       → HTTP %d %s #%s\n", $b->estado, $b->cuerpo['codigo'] ?? $b->cuerpo['error'], $b->cuerpo['id'] ?? '-');
printf("  dr-ruiz con su propia clave                                            → HTTP %d %s (lo correcto)\n", $sinClave->estado, $sinClave->cuerpo['error'] ?? $sinClave->cuerpo['codigo']);
printf("  prescriptor de #%d: %s · trazas a nombre de dr-ruiz: %d\n\n", $a->cuerpo['id'], $p['repositorio']->todas()[0]->prescriptor,
    count(array_filter($p['auditoria']->movimientos(), static fn (array $t): bool => $t['usuario'] === 'dr-ruiz')));

echo 'AR-03 · La coincidencia por NOMBRE es exacta: un alérgeno escrito de otra forma no bloquea ni advierte' . PHP_EOL;
foreach (['Amoxicilina trihidrato', 'Amoxicilina (sal sódica)', 'Penicilina G'] as $nombre) {
    [$k, $p] = $sistema();
    $p['alergias']->agregar('P-005', new Alergia(null, $nombre, 'allergy', 'severe', 'active', 'anafilaxia'));
    $r = $pedir($k, 'POST', '/verificaciones', 'token-dr-lopez', (string) json_encode(['paciente' => 'P-005', 'items' => [$item('AMOX500')]]));
    printf("  P-005 con alergia GRAVE activa registrada como «%s»; previa de Amoxicilina 500 mg → %s\n", $nombre, $r->cuerpo['codigo']);
}
[$k, $p] = $sistema();
$p['alergias']->agregar('P-005', new Alergia(null, 'Amoxicilina', 'allergy', 'severe', 'active', 'anafilaxia'));
printf("  control: la misma alergia registrada como «Amoxicilina» → %s\n\n",
    $pedir($k, 'POST', '/verificaciones', 'token-dr-lopez', (string) json_encode(['paciente' => 'P-005', 'items' => [$item('AMOX500')]]))->cuerpo['codigo']);

echo 'AR-04 · El contrato declara «integer» (JSON Schema acepta 7.0) y el código rechaza 7.0' . PHP_EOL;
[$k] = $sistema();
foreach (['"dias": 7' => '7', '"dias": 7.0' => '7.0', '"frecuencia_horas": 8.0' => '8.0'] as $etiqueta => $_) {
    $crudo = '{"paciente": "P-005", "items": [{"medicamento": "PARA500", "dosis": 500, "unidad": "mg", "frecuencia_horas": 8, "via": "oral", "dias": 7}]}';
    $crudo = str_contains($etiqueta, 'frecuencia') ? str_replace('"frecuencia_horas": 8', '"frecuencia_horas": 8.0', $crudo) : str_replace('"dias": 7', $etiqueta, $crudo);
    $r = $pedir($k, 'POST', '/verificaciones', 'token-dr-lopez', $crudo);
    printf("  ítem con %-24s → HTTP %d %s\n", $etiqueta, $r->estado, $json($r->cuerpo));
}
$yaml = (string) file_get_contents("$micro/api/openapi.yaml");
printf("  el contrato: %s\n\n", preg_match('/dias: \{type: integer[^}]*\}/', $yaml, $m) ? $m[0] : '(no encontrado)');

echo 'AR-05 · Anular no es idempotente: el reintento de una anulación que SÍ llegó responde 409 sin decir quién anuló' . PHP_EOL;
[$k] = $sistema();
$id = $pedir($k, 'POST', '', 'token-dr-lopez', (string) json_encode(['paciente' => 'P-005', 'items' => [$item('PARA500')]]), 'ar05')->cuerpo['id'];
$motivo = (string) json_encode(['motivo' => 'Pauta duplicada con la de urgencias']);
$r1 = $pedir($k, 'POST', "/$id/anulacion", 'token-dr-lopez', $motivo);
$r2 = $pedir($k, 'POST', "/$id/anulacion", 'token-dr-lopez', $motivo);
printf("  1.ª anulación → HTTP %d %s · el reintento idéntico → HTTP %d %s\n  claves del cuerpo del 409: [%s] (el cliente necesita otra petición para saber si fue suya)\n\n",
    $r1->estado, $r1->cuerpo['codigo'], $r2->estado, $r2->cuerpo['error'], implode(', ', array_keys($r2->cuerpo)));

echo 'AR-06 · El hallazgo y la consulta devuelven el CÓDIGO del alérgeno sin su nombre' . PHP_EOL;
[$k] = $sistema();
$r = $pedir($k, 'POST', '/verificaciones', 'token-dr-lopez', (string) json_encode(['paciente' => 'P-001', 'items' => [$item('CEFA500')]]));
printf("  previa P-001 (alergia a Amoxicilina, código J01CA04) con Cefalexina → %s · hallazgo: %s\n", $r->cuerpo['codigo'], $json($r->cuerpo['hallazgos'][0]));
$e = $pedir($k, 'POST', '', 'token-dr-lopez', (string) json_encode(['paciente' => 'P-001', 'items' => [$item('CEFA500')], 'justificacion' => 'Sin alternativa: cultivo sensible solo a cefalosporinas']), 'ar06');
$c = $pedir($k, 'GET', '/' . $e->cuerpo['id'], 'token-farmacia-norte');
printf("  farmacia consulta #%d → alergenos_evaluados: %s (sin nombre)\n\n", $e->cuerpo['id'], json_encode($c->cuerpo['verificacion']['alergenos_evaluados']));

echo 'AR-07 · El tipo de la alergia (allergy / intolerance) no participa en ninguna regla' . PHP_EOL;
foreach (['allergy', 'intolerance'] as $tipo) {
    [$k, $p] = $sistema();
    $p['alergias']->agregar('P-003', new Alergia('J01CA04', 'Amoxicilina', $tipo, 'mild', 'active', 'náuseas'));
    $r = $pedir($k, 'POST', '/verificaciones', 'token-dr-lopez', (string) json_encode(['paciente' => 'P-003', 'items' => [$item('AMOX500')]]));
    printf("  P-003 con «%s» leve (náuseas) a Amoxicilina; previa de Amoxicilina → %s\n", $tipo, $r->cuerpo['codigo']);
}
$usos = 0;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$micro/src", FilesystemIterator::SKIP_DOTS)) as $f) {
    $usos += preg_match_all('/\$(a|alergia)->tipo\b/', (string) file_get_contents((string) $f));
}
printf('  lecturas de Alergia::tipo ($a->tipo o $alergia->tipo) en todo src/: %d' . PHP_EOL, $usos);

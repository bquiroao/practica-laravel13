<?php

declare(strict_types=1);

/**
 * Semana 13 · Reproduce los hallazgos de la autorrevisión del autor (AR-xx) contra el kernel REAL
 * del Micro-HIS Pacientes (adaptadores en memoria). Cada bloque imprime la evidencia que cita el hallazgo.
 *
 * Uso: php herramientas/verificar-autorrevision.php   (desde docs/modulos/pacientes/semana-13)
 */
$micro = dirname(__DIR__, 2) . '/micro-his-pacientes';
require $micro . '/bootstrap/contenedor.php';
foreach (glob($micro . '/tests/Dobles/*.php') as $archivo) {
    require_once $archivo;
}

use Pacientes\Presentacion\Http\Solicitud;
use Pacientes\Pruebas\Dobles\Escenario;

$http = static function (object $e, string $metodo, string $ruta, string $token, string $hospital, ?array $cuerpo = null, array $consulta = []) {
    return $e->kernel->manejar(Escenario::http($metodo, $ruta, $token, $cuerpo, $consulta, [], $hospital));
};
$json = static fn (array $c): string => json_encode($c, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$ana = Escenario::json(Escenario::datosAna());

echo "AR-01 · El punto de entrada real siempre acepta los tokens de demostración" . PHP_EOL;
$config = ['persistencia' => 'memoria'] + (require "$micro/config/config.php");
$kernel = crearKernel($config);
$r = $kernel->manejar(new Solicitud('GET', '/api/v1/pacientes', ['X-Tenant-ID' => 'hosp-oriente', 'Authorization' => 'Bearer token-supervisor-oriente'], ['nombre' => 'lopez']));
printf("  crearKernel() + «Bearer token-supervisor-oriente» → HTTP %d · tokens demo configurados: %d · variable de entorno que lo impida: %s\n\n",
    $r->estado, count($config['tokens_demo']), getenv('MICROHIS_ENTORNO') === false ? 'ninguna' : getenv('MICROHIS_ENTORNO'));

echo "AR-02 · El 409 por documento revela el código de un paciente de OTRO hospital" . PHP_EOL;
$e = Escenario::crear();
$http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', 'hosp-oriente', $ana);
$r = $http($e, 'POST', '/api/v1/pacientes', 'token-admision-norte', 'hosp-norte', $ana);
printf("  admisión de hosp-NORTE registra el DPI de una paciente de hosp-ORIENTE → HTTP %d %s\n", $r->estado, $json($r->cuerpo));
$r2 = $http($e, 'GET', '/api/v1/pacientes/P-000001', 'token-admision-norte', 'hosp-norte');
printf("  la misma admisión abre la ficha P-000001 que le dio el 409 → HTTP %d %s\n\n", $r2->estado, $json($r2->cuerpo));

echo "AR-03 · Los candidatos de POSIBLE_DUPLICADO incluyen pacientes de otros hospitales" . PHP_EOL;
$sinDoc = Escenario::json(Escenario::datosAna(['tipo_documento' => null, 'numero_documento' => null]));
$r = $http($e, 'POST', '/api/v1/pacientes', 'token-admision-norte', 'hosp-norte', $sinDoc);
printf("  admisión de hosp-NORTE registra a una homónima sin documento → HTTP %d %s\n\n", $r->estado, $json($r->cuerpo));

echo "AR-04 · Quien tiene el DPI de una paciente protegida confirma que está en el hospital" . PHP_EOL;
$e = Escenario::crear();
$http($e, 'POST', '/api/v1/pacientes', 'token-supervisor-oriente', 'hosp-oriente', Escenario::json(Escenario::datosAna(), ['identidad_protegida' => true, 'motivo' => 'Orden judicial']));
$r = $http($e, 'GET', '/api/v1/pacientes', 'token-clinico-oriente', 'hosp-oriente', null, ['tipo_documento' => 'DPI', 'numero_documento' => Escenario::DPI_ANA]);
$otro = $http($e, 'GET', '/api/v1/pacientes', 'token-clinico-oriente', 'hosp-oriente', null, ['tipo_documento' => 'DPI', 'numero_documento' => Escenario::DPI_NUEVO]);
printf("  clínico busca el DPI de la protegida → total=%d %s\n  clínico busca un DPI que no existe   → total=%d (la diferencia confirma la presencia)\n\n",
    $r->cuerpo['total'], $json($r->cuerpo['resultados'][0] ?? []), $otro->cuerpo['total']);

echo "AR-05 · Extensiones PHP usadas por src/ y declaradas en el README" . PHP_EOL;
$usadas = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$micro/src", FilesystemIterator::SKIP_DOTS)) as $f) {
    $codigo = (string) file_get_contents($f->getPathname());
    foreach (['mbstring' => '/\bmb_\w+\(/', 'sodium' => '/\bsodium_\w+\(/', 'pdo' => '/\bPDO\b/', 'json' => '/\bjson_(en|de)code\(/'] as $ext => $patron) {
        if (preg_match($patron, $codigo)) {
            $usadas[$ext] = true;
        }
    }
}
$readme = strtolower((string) file_get_contents("$micro/README.md"));
foreach (array_keys($usadas) as $ext) {
    printf("  %-9s usada en src/ · declarada en README: %s\n", $ext, str_contains($readme, $ext) ? 'sí' : 'NO');
}
echo PHP_EOL;

echo "AR-06 · Las claves de idempotencia no vencen" . PHP_EOL;
$e = Escenario::crear();
for ($i = 1; $i <= 50; $i++) {
    $http2 = $e->kernel->manejar(Escenario::http('POST', '/api/v1/pacientes', 'token-admision-oriente',
        Escenario::json(['nombres' => "Paciente $i", 'apellidos' => 'Prueba', 'fecha_nacimiento' => '2000-01-01', 'sexo' => 'unknown']), [], ['Idempotency-Key' => sprintf('clave-%04d', $i)]));
}
$ref = new ReflectionObject($e->idempotencia);
$props = array_map(static fn (ReflectionProperty $p): string => $p->getName(), $ref->getProperties());
echo "  50 registros con clave → el almacén conserva todas; propiedades del almacén: " . implode(', ', $props)
    . " (ninguna de vencimiento); el puerto AlmacenIdempotencia no define limpieza" . PHP_EOL;

echo PHP_EOL . "AR-07 · El DPI solo valida longitud y departamento (no el dígito verificador ni el municipio)" . PHP_EOL;
$e = Escenario::crear();
foreach ([['0000000000101', 'ceros + departamento 01'], ['1234567890199', 'departamento 01, municipio 99 (Guatemala tiene 17)']] as [$dpi, $caso]) {
    $r = $http($e, 'POST', '/api/v1/pacientes', 'token-admision-oriente', 'hosp-oriente',
        Escenario::json(['nombres' => 'Prueba', 'apellidos' => $caso, 'fecha_nacimiento' => '2000-01-01', 'sexo' => 'unknown', 'tipo_documento' => 'DPI', 'numero_documento' => $dpi]));
    printf("  DPI %s (%s) → HTTP %d %s\n", $dpi, $caso, $r->estado, $r->estado === 201 ? 'aceptado' : json_encode($r->cuerpo, JSON_UNESCAPED_UNICODE));
}

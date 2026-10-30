<?php
declare(strict_types=1);

/*
 * Prueba la API en vivo contra el servidor de desarrollo y guarda la evidencia en UTF-8.
 *   php scripts/probar-api.php --base=http://127.0.0.1:8085 --evidencia=../semana-05/evidencia/api-en-vivo.txt
 * Se usa PHP y no Invoke-RestMethod porque PowerShell 5 oculta el cuerpo de los errores HTTP.
 * Resultados reproducibles: servidor con CATALOGO_ALMACEN=memoria, o base recién creada con --reiniciar.
 */

$opciones = ['base' => 'http://127.0.0.1:8085', 'evidencia' => null];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--(base|evidencia)=(.+)$/', $arg, $m) === 1) {
        $opciones[$m[1]] = $m[2];
    }
}

function llamar(string $base, string $metodo, string $ruta, ?string $token, ?array $cuerpo, int $n): array
{
    $cabeceras = [sprintf('X-Request-Id: evidencia-%04d', $n)];
    if ($token !== null) {
        $cabeceras[] = "Authorization: Bearer $token";
    }
    if ($cuerpo !== null) {
        $cabeceras[] = 'Content-Type: application/json';
    }
    $contexto = stream_context_create(['http' => [
        'method' => $metodo, 'header' => implode("\r\n", $cabeceras), 'ignore_errors' => true, 'timeout' => 5,
        'content' => $cuerpo === null ? '' : json_encode($cuerpo, JSON_UNESCAPED_UNICODE),
    ]]);
    $respuesta = @file_get_contents($base . $ruta, false, $contexto);
    if ($respuesta === false) {
        fwrite(STDERR, "No se pudo conectar a $base. ¿Está corriendo php -S 127.0.0.1:8085 -t public?" . PHP_EOL);
        exit(1);
    }
    $estado = (int) (preg_match('#^HTTP/\S+ (\d{3})#', $http_response_header[0] ?? '', $m) === 1 ? $m[1] : 0);
    $requestId = '';
    foreach ($http_response_header as $h) {
        if (stripos($h, 'X-Request-Id:') === 0) {
            $requestId = trim(substr($h, 13));
        }
    }
    return [$estado, $requestId, $respuesta];
}

$alta = ['nombre' => 'Loratadina 10 mg tableta', 'principio_activo' => 'Loratadina', 'concentracion_valor' => '10',
    'concentracion_unidad' => 'mg', 'forma_farmaceutica' => 'TABLETA', 'via_administracion' => 'ORAL', 'presentacion' => 'Caja con 10 tabletas'];
$casos = [
    ['Búsqueda de vigentes (consulta clínica)', 'GET', '/api/v1/medicamentos?q=amoxi', 'tok-consulta-norte-0001', null, 200],
    ['Sin token', 'GET', '/api/v1/medicamentos?q=amoxi', null, null, 401],
    ['Rol sin permiso para registrar', 'POST', '/api/v1/medicamentos', 'tok-consulta-norte-0001', $alta, 403],
    ['Alta válida', 'POST', '/api/v1/medicamentos', 'tok-qf-norte-0001', $alta, 201],
    ['Alta duplicada de Amoxicilina 500 mg', 'POST', '/api/v1/medicamentos', 'tok-qf-norte-0001', ['nombre' => 'Amoxicilina 500 mg', 'principio_activo' => 'amoxicilina', 'concentracion_valor' => '500', 'concentracion_unidad' => 'mg', 'forma_farmaceutica' => 'CAPSULA', 'via_administracion' => 'ORAL', 'presentacion' => 'Caja'], 409],
    ['Datos inválidos', 'POST', '/api/v1/medicamentos', 'tok-qf-norte-0001', ['nombre' => 'X'] + $alta, 422],
    ['Otro hospital (tenant)', 'GET', '/api/v1/medicamentos/MED-000102', 'tok-qf-sur-0001', null, 404],
    ['Historial (auditor)', 'GET', '/api/v1/medicamentos/MED-000104/versiones', 'tok-auditor-norte-0001', null, 200],
    ['Eliminar código usado', 'DELETE', '/api/v1/medicamentos/MED-000101', 'tok-qf-norte-0001', null, 409],
];

$lineas = ['Prueba de la API en vivo - Catálogo de medicamentos', 'Base: ' . $opciones['base'] . ' · ' . date('Y-m-d H:i:s T'), ''];
$bien = 0;
foreach ($casos as $n => [$nombre, $metodo, $ruta, $token, $cuerpo, $esperado]) {
    [$estado, $requestId, $texto] = llamar($opciones['base'], $metodo, $ruta, $token, $cuerpo, $n + 1);
    $ok = $estado === $esperado;
    $bien += $ok ? 1 : 0;
    $json = json_decode($texto, true);
    $resumen = isset($json['error']) ? $json['error']['codigo'] . ': ' . $json['error']['mensaje'] : (isset($json['datos'][0]) ? count($json['datos']) . ' resultado(s)' : ($json['datos']['codigo'] ?? ''));
    $lineas[] = sprintf('%s %-6s %-46s -> %d (esperado %d) id=%s', $ok ? 'OK   ' : 'FALLA', $metodo, $ruta, $estado, $esperado, $requestId);
    $lineas[] = "      $nombre · " . mb_strimwidth($resumen, 0, 110, '…');
}
$lineas[] = '';
$lineas[] = sprintf('Resumen: %d de %d respuestas con el código esperado', $bien, count($casos));
$salida = implode(PHP_EOL, $lineas) . PHP_EOL;
echo $salida;
if ($opciones['evidencia'] !== null) {
    @mkdir(dirname($opciones['evidencia']), 0777, true);
    file_put_contents($opciones['evidencia'], $salida);
    echo 'Evidencia escrita en ' . $opciones['evidencia'] . PHP_EOL;
}
exit($bien === count($casos) ? 0 : 1);

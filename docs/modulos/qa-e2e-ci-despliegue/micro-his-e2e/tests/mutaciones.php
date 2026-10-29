<?php

declare(strict_types=1);

/*
 * Defectos sembrados a propósito (16 de la semana 3 y 5 de la semana 4): cada mutación copia el módulo, cambia UNA cosa (una regla del dominio, un paso del caso
 * de uso o una restricción del esquema) y corre la suite sobre la copia. Si la suite sigue en verde, falta una prueba.
 *   E2E_DB_DSN=... php tests/mutaciones.php      (sin base, las mutaciones del esquema sobreviven: corren solo con PostgreSQL)
 * Código de salida: cantidad de mutaciones que sobreviven.
 */

$mutaciones = [
    'M1 los datos de un escenario no tienen que ser sintéticos (R-01)' => ['src/Dominio/Modelo/EscenarioE2E.php',
        "if (!preg_match('/^SINT-[A-Z0-9-]{2,40}$/', \$datos)) {", 'if (false) {'],
    'M2 el plan no exige los críticos (R-02)' => ['src/Dominio/Modelo/Version.php', '$e->critico || in_array($e->modulo, $this->modulos, true)', 'in_array($e->modulo, $this->modulos, true)'],
    'M3 el plan congelado se puede cambiar (R-02)' => ['src/Dominio/Modelo/Version.php', "if (\$this->planCongelado) {\n            throw", "if (false) {\n            throw"],
    'M4 se ejecuta sobre cualquier digest (R-03)' => ['src/Dominio/Modelo/Ejecucion.php', 'if ($digest !== $version->digest) {', 'if (false) {'],
    'M5 un PASA tras reintentar cuenta como PASA (R-06)' => ['src/Dominio/Modelo/ResultadoEscenario.php', 'if ($resultado === Resultado::PASA && $intentos > 1) {', 'if (false) {'],
    'M6 se admite un segundo resultado del mismo escenario (R-04)' => ['src/Dominio/Modelo/Ejecucion.php', 'if ($previo->escenario === $r->escenario) {', 'if (false) {'],
    'M7 el gate no compara el digest (R-03)' => ['src/Dominio/Modelo/QualityGate.php', "'digest' => \$ejecucion->digest === \$version->digest", "'digest' => true"],
    'M8 el gate acepta un crítico que no pasa (R-09a)' => ['src/Dominio/Modelo/QualityGate.php', 'if ($r->critico && $r->resultado !== Resultado::PASA) {', 'if (false) {'],
    'M9 el gate no mira la antigüedad (R-09e)' => ['src/Dominio/Modelo/QualityGate.php', "'e_antiguedad' => \$horas <= \$politica->antiguedadMaximaHoras", "'e_antiguedad' => true"],
    'M10 la evidencia se guarda DESPUÉS de la base (no fail-closed)' => ['src/Aplicacion/CasosDeUso/ReportarResultado.php',
        '$evidencias = array_map(fn (array $a): Evidencia => $this->almacen->guardar((string) $a[\'tipo\'], (string) $a[\'bytes\']), $artefactos);',
        '$evidencias = []; try { array_map(fn (array $a): Evidencia => $this->almacen->guardar((string) $a[\'tipo\'], (string) $a[\'bytes\']), $artefactos); } catch (\Throwable) {}'],
    'M11 sin idempotencia (R-12)' => ['src/Aplicacion/CasosDeUso/ReportarResultado.php', '$previo = $this->ejecuciones->resultadoPorClave($tenant, $clave);', '$previo = null;'],
    'M12 el almacén se usa antes de revisar las reglas (huérfanos)' => ['src/Aplicacion/CasosDeUso/ReportarResultado.php',
        "            (clone \$ejecucion)->agregar(\$sinEvidencias);", "            array_map(fn (\$a) => \$this->almacen->guardar('log', (string) (\$a['bytes'] ?? 'x')), \$artefactos);\n            (clone \$ejecucion)->agregar(\$sinEvidencias);"],
    'M13 la traza va fuera de la transacción' => ['src/Aplicacion/CasosDeUso/ReportarResultado.php',
        "                \$this->auditoria->registrar('RESULTADO_REPORTADO', \$tenant, \$usuario, \"ejecucion:\$ejecucionId/\" . \$r->escenario);\n", ''],
    'M14 los resultados se pueden modificar (esquema)' => ['database/schema.sql',
        'CREATE TRIGGER resultados_solo_agregar BEFORE UPDATE OR DELETE ON resultados FOR EACH ROW EXECUTE FUNCTION solo_agregar();', ''],
    'M15 la base acepta una ejecución con otro digest (esquema)' => ['database/schema.sql',
        "        IF NEW.digest IS DISTINCT FROM (SELECT v.digest FROM versiones v WHERE v.tenant = NEW.tenant AND v.numero = NEW.version) THEN",
        '        IF false THEN'],
    'M16 el adaptador PDO no traduce la clave repetida' => ['src/Persistencia/Pdo/PdoRepositorioEjecuciones.php',
        "return \$e->getCode() === '23505' && str_contains(\$e->getMessage(), 'clave') ? new ReglaRechazada('CLAVE_REUTILIZADA') : parent::traducir(\$e);",
        'return parent::traducir($e);'],
    // Semana 4: Repository, MVC y repositorio compartido.
    'M17 el adaptador en memoria no condiciona el cierre al estado anterior' => ['src/Persistencia/EnMemoria/RepositorioEjecucionesEnMemoria.php',
        "if (\$guardada === null || \$guardada->estado() !== Ejecucion::EN_CURSO) {", 'if (false) {'],
    'M18 el formulario no revisa los tipos' => ['src/Presentacion/Http/Formularios/Formulario.php', "        if (\$mal !== []) {
            throw new EntradaInvalida('CUERPO_INVALIDO', \$mal);",
        "        if (false) {
            throw new EntradaInvalida('CUERPO_INVALIDO', \$mal);"],
    'M19 la evaluación no se publica en el repositorio compartido' => ['database/integracion-compartida.sql',
        'CREATE TRIGGER evaluaciones_publicar AFTER INSERT ON e2e.evaluaciones FOR EACH ROW EXECUTE FUNCTION e2e.publicar_evaluacion();', ''],
    'M20 el repositorio compartido no filtra por hospital' => ['database/integracion-compartida.sql',
        "    USING (tenant = current_setting('his.tenant', true));", '    USING (true);'],
    'M21 el controlador de versiones se salta el formulario' => ['src/Presentacion/Http/Controladores/VersionesControlador.php',
        '            $datos = FormularioVersion::registrar($s);', '            $datos = json_decode($s->cuerpo, true) ?? [];'],
    // Semana 5: cliente-servidor, contrato y observabilidad.
    'M22 el reportador cambia la clave en cada reintento (R-12)' => ['src/Cliente/ReportadorCi.php', "'Idempotency-Key' => \$pedido['clave'],", "'Idempotency-Key' => \$pedido['clave'] . \$i,"],
    'M23 el reportador reintenta un 4xx' => ['src/Cliente/ReportadorCi.php', "if (\$r['estado'] < 500 && \$r['estado'] !== 429) {", 'if (false) {'],
    'M24 el contrato no documenta el 413 de reportar' => ['api/openapi.yaml', "        '413':\n          \$ref: '#/components/responses/Error'\n", ''],
    'M25 el kernel acepta cualquier X-Request-ID' => ['src/Presentacion/Http/Kernel.php', "preg_match('/^[A-Za-z0-9-]{8,64}\$/', \$propio)", "preg_match('/^.+\$/s', \$propio)"],
];

function copiar(string $origen, string $destino): void
{
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($origen, FilesystemIterator::SKIP_DOTS)) as $f) {
        $d = $destino . substr((string) $f, strlen($origen));
        @mkdir(dirname($d), 0777, true);
        copy((string) $f, $d);
    }
}

$origen = dirname(__DIR__);
$sobreviven = 0;
foreach ($mutaciones as $nombre => [$archivo, $buscar, $reemplazo]) {
    $copia = sys_get_temp_dir() . '/micro-e2e-mut-' . getmypid() . '-' . md5($nombre);
    copiar($origen, $copia);
    $codigo = str_replace("\r\n", "\n", (string) file_get_contents("$copia/$archivo"));
    if (substr_count($codigo, $buscar) !== 1) {
        exit("$nombre: el patrón no aparece exactamente una vez en $archivo\n");
    }
    file_put_contents("$copia/$archivo", str_replace($buscar, $reemplazo, $codigo));
    $salida = [];
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$copia/tests/ejecutar.php") . ' 2>&1', $salida, $codigoSalida);
    $fallan = array_map(static fn (string $l): string => preg_replace('/^\[FALLA\]\s+(\S+).*$/', '$1', $l), preg_grep('/^\[FALLA\]/', $salida) ?: []);
    $sobreviven += $codigoSalida === 0 ? 1 : 0;
    printf("[%s] %-66s %s\n", $codigoSalida === 0 ? 'SOBREVIVE' : 'DETECTA  ', $nombre, $fallan === [] ? '' : 'falla: ' . implode(', ', $fallan));
}
echo str_repeat('-', 100) . "\nMutaciones: " . count($mutaciones) . '  Detectadas: ' . (count($mutaciones) - $sobreviven) . "  Sobreviven: $sobreviven\n";
exit($sobreviven);

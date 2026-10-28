<?php

declare(strict_types=1);

/*
 * ¿Las pruebas detectan defectos reales? Cada mutación introduce UN defecto en una copia temporal del
 * Micro-HIS y ejecuta tests/ejecutar.php: la suite debe fallar. Con PRESCRIPCIONES_DB_DSN corren también
 * las de integración (las mutaciones del SQL y del esquema solo las detectan ellas).
 *   php tests/mutaciones.php
 */

$mutaciones = [
    'M1 el adaptador PDO devuelve vacío si allergies falla (fail-open)' => ['src/Persistencia/Pdo/PdoFuenteAlergias.php',
        <<<'X'
        throw new FuenteAlergiasNoDisponible('El expediente no respondió.', 0, $e);
        X, '$filas = [];'],
    'M2 la aplicación trata la fuente caída como «sin alergias»' => ['src/Aplicacion/Servicios/EvaluacionClinica.php',
        '$expediente = $this->alergias->alergiasDe($paciente, $hospital);',
        'try { $expediente = $this->alergias->alergiasDe($paciente, $hospital); } catch (FuenteAlergiasNoDisponible) { $expediente = new ExpedienteAlergias(true, []); }'],
    'M3 la coincidencia directa deja de bloquear' => ['src/Dominio/Reglas/VerificadorAlergias.php',
        '$a->severidad, true, $a->reaccion', '$a->severidad, false, $a->reaccion'],
    'M4 la severidad «unknown» se trata como leve' => ['src/Dominio/Modelo/Alergia.php',
        <<<'X'
        ['life_threatening', 'unknown']
        X, <<<'X'
        ['life_threatening']
        X],
    'M5 la dosis máxima se duplica' => ['src/Dominio/Reglas/ValidadorPrescripcion.php',
        '> $m->dosisMaximaDiaria', '> $m->dosisMaximaDiaria * 2'],
    'M6 no se exige confirmar «sin alergias conocidas»' => ['src/Dominio/Reglas/PoliticaEmision.php',
        'if (!$expediente->evaluado && !$solicitud->confirmaSinAlergias) {', 'if (false) {'],
    'M7 la advertencia no exige el permiso override' => ['src/Dominio/Reglas/PoliticaEmision.php',
        '|| !$tieneOverride()', '|| false'],
    'M8 sin idempotencia' => ['src/Aplicacion/CasosDeUso/EmitirPrescripcion.php',
        'if ($previa !== null) {', 'if (false) {'],
    'M9 no se verifica el hospital del paciente' => ['src/Aplicacion/CasosDeUso/EmitirPrescripcion.php',
        'if (!$this->pacientes->existeEnHospital($s->paciente, $s->hospital)) {', 'if (false) {'],
    'M10 el bloqueo no deja traza' => ['src/Aplicacion/CasosDeUso/EmitirPrescripcion.php',
        <<<'X'
        $this->trazarAparte('PRESCRIPCION_BLOQUEADA'
        X, <<<'X'
        if (false) $this->trazarAparte('PRESCRIPCION_BLOQUEADA'
        X],
    'M11 el kernel no compara X-Tenant-ID con el token' => ['src/Presentacion/Http/Kernel.php',
        'if ($tenant !== $identidad->hospital) {', 'if (false) {'],
    'M12 el SQL de la consulta ignora el hospital' => ['src/Persistencia/Pdo/PdoRepositorioPrescripciones.php',
        'FROM prescriptions WHERE hospital = :hospital AND prescription_id = :id',
        'FROM prescriptions WHERE (hospital = :hospital OR true) AND prescription_id = :id'],
    'M13 la carrera de idempotencia no se traduce a ClaveEnUso' => ['src/Persistencia/Pdo/PdoRepositorioPrescripciones.php',
        <<<'X'
        $e->getCode() === '23505'
        X, 'false'],
    'M14 el esquema sin el trigger de inmutabilidad' => ['database/schema.sql',
        "CREATE TRIGGER prescriptions_inmutable BEFORE UPDATE OR DELETE ON prescriptions\n    FOR EACH ROW EXECUTE FUNCTION rechazar_cambio_prescripcion();",
        ''],
    // Semana 4: MVC, adaptadores InMemory de producción y el hallazgo H-4.
    'M15 el controlador arma él mismo la respuesta de error' => ['src/Presentacion/Http/Controladores/PrescripcionesControlador.php',
        'return ErrorVista::entradaInvalida($e);', <<<'X'
        return Respuesta::json(400, ['error' => $e->codigo]);
        X],
    'M16 el repositorio InMemory ignora el hospital en porId' => ['src/Persistencia/EnMemoria/RepositorioPrescripcionesEnMemoria.php',
        'return $p !== null && $p->hospital === $hospital ? $this->conSuAnulacion($p) : null;', 'return $p === null ? null : $this->conSuAnulacion($p);'],
    'M17 el repositorio InMemory acepta una clave repetida' => ['src/Persistencia/EnMemoria/RepositorioPrescripcionesEnMemoria.php',
        'if ($this->porClave($prescripcion->hospital, $prescripcion->clave) !== null) {', 'if (false) {'],
    'M18 regresión de H-4: la dosis no se normaliza' => ['src/Dominio/Modelo/Prescripcion.php',
        <<<'X'
        array_replace($i, ['dosis' => (float) $i['dosis']])
        X, '$i'],
    'M19 la vista pone Location también en un reintento (200)' => ['src/Presentacion/Http/Vistas/PrescripcionVista.php',
        '$r->tipo === Resultado::CREADO ? [', 'true ? ['],
    // Semana 5: Expediente remoto, cliente, observabilidad y contrato.
    'M20 HttpFuenteAlergias trata un estado ≠ 200 como «sin alergias»' => ['src/Persistencia/Http/HttpFuenteAlergias.php',
        'throw new \RuntimeException("estado $estado");', 'return new ExpedienteAlergias(true, []);'],
    'M21 HttpFuenteAlergias acepta «evaluado=false» con alergias' => ['src/Persistencia/Http/HttpFuenteAlergias.php',
        "if (!\$datos['evaluado'] && \$alergias !== []) {", 'if (false) {'],
    'M22 el circuito nunca se abre' => ['src/Persistencia/Http/CircuitoInterruptor.php',
        '$this->fallasSeguidas >= $this->umbral', 'false'],
    'M23 el cliente reintenta también los 4xx' => ['src/Cliente/ClientePrescripciones.php',
        "if (\$r['estado'] < 500) {", "if (\$r['estado'] < 400) {"],
    'M24 el cliente informa INCIERTO cuando el servidor afirmó que no emitió' => ['src/Cliente/ClientePrescripciones.php',
        "=== 'VERIFICACION_NO_DISPONIBLE' ? ResultadoCliente::RECHAZADO", "=== 'X' ? ResultadoCliente::RECHAZADO"],
    'M25 el kernel acepta cualquier X-Request-ID (inyección de cabeceras)' => ['src/Presentacion/Http/Kernel.php',
        'preg_match(self::PATRON_REQUEST_ID, $recibido)', 'true'],
    'M26 el registro de acceso guarda el cuerpo de la petición' => ['src/Presentacion/Http/Kernel.php',
        "'tenant' => \$identidad?->hospital,", "'tenant' => \$identidad?->hospital, 'cuerpo' => \$solicitud->cuerpo,"],
    'M27 el contrato documenta otro permiso para consultar' => ['api/openapi.yaml',
        'x-permiso: prescripcion.consultar', 'x-permiso: prescripcion.emitir'],
    // Semana 7: la política de emisión del Dominio.
    'M28 el mínimo de la justificación baja a 19' => ['src/Dominio/Reglas/PoliticaEmision.php',
        'mb_strlen($texto) < self::MIN_JUSTIFICACION', 'mb_strlen($texto) < self::MIN_JUSTIFICACION - 1'],
    'M29 se consulta el permiso override aunque no haya advertencia' => ['src/Dominio/Reglas/PoliticaEmision.php',
        '$confirmacion = !$expediente->evaluado && $solicitud->confirmaSinAlergias;',
        '$confirmacion = !$expediente->evaluado && $solicitud->confirmaSinAlergias; $tieneOverride();'],
    'M30 la prescripción guarda la justificación cruda, no la de la decisión' => ['src/Dominio/Modelo/Prescripcion.php',
        '$decision->justificacion,', '$s->justificacion,'],
    // Semana 6: cambio práctico (anulación con motivo).
    'M31 la anulación no exige el motivo mínimo' => ['src/Dominio/Reglas/PoliticaAnulacion.php',
        "mb_strlen(trim(\$motivo)) < self::MIN_MOTIVO ? 'MOTIVO_REQUERIDO' : null", 'null'],
    'M32 el repositorio InMemory acepta dos anulaciones' => ['src/Persistencia/EnMemoria/RepositorioPrescripcionesEnMemoria.php',
        'if (isset($this->anulaciones[$anulacion->prescripcionId])) {', 'if (false) {'],
    'M33 la anulación no deja traza' => ['src/Aplicacion/CasosDeUso/AnularPrescripcion.php',
        "\$this->auditoria->registrar('PRESCRIPCION_ANULADA'", "if (false) \$this->auditoria->registrar('PRESCRIPCION_ANULADA'"],
];

$origen = dirname(__DIR__);
$detectadas = 0;
foreach ($mutaciones as $nombre => [$archivo, $buscar, $reemplazo]) {
    $copia = sys_get_temp_dir() . '/micro-presc-mut-' . getmypid() . '-' . md5($nombre);
    copiar($origen, $copia);
    $codigo = str_replace("\r\n", "\n", (string) file_get_contents("$copia/$archivo"));
    if (!str_contains($codigo, $buscar)) {
        echo "[ERROR    ] $nombre: el texto a mutar no está en $archivo" . PHP_EOL;
        borrar($copia);
        continue;
    }
    file_put_contents("$copia/$archivo", str_replace($buscar, $reemplazo, $codigo));
    exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=0 -d log_errors=0 ' . escapeshellarg("$copia/tests/ejecutar.php"), $salida, $codigoSalida);
    $fallan = array_values(array_filter($salida, static fn (string $l): bool => str_starts_with($l, '[FALLA]')));
    $ids = array_map(static fn (string $l): string => preg_split('/\s+/', $l)[1], $fallan);
    $detectada = $codigoSalida !== 0;
    $detectadas += $detectada ? 1 : 0;
    printf("[%s] %s %s\n", $detectada ? 'DETECTA  ' : 'SOBREVIVE', mb_str_pad($nombre, 62), $detectada ? 'falla: ' . implode(', ', $ids) : '');
    unset($salida);
    borrar($copia);
}
echo str_repeat('-', 100) . PHP_EOL . sprintf('Mutaciones: %d  Detectadas: %d  Sobreviven: %d', count($mutaciones), $detectadas, count($mutaciones) - $detectadas) . PHP_EOL;
exit($detectadas === count($mutaciones) ? 0 : 1);

function copiar(string $de, string $a): void
{
    @mkdir($a, 0777, true);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($de, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $item) {
        $destino = $a . substr($item->getPathname(), strlen($de));
        $item->isDir() ? @mkdir($destino, 0777, true) : copy($item->getPathname(), $destino);
    }
}

function borrar(string $directorio): void
{
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directorio, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($directorio);
}

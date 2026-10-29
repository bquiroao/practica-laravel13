<?php

declare(strict_types=1);

/*
 * ¿Las pruebas detectan defectos reales? Cada mutación introduce UN defecto en una copia temporal del
 * Micro-HIS y ejecuta tests/ejecutar.php: la suite debe fallar. Con QA_DB_DSN corren también las de
 * integración (las mutaciones del SQL y del esquema solo las detectan ellas).
 *   php tests/mutaciones.php
 */

$mutaciones = [
    'M1 el autor puede aprobar su propio caso (R-02)' => ['src/Dominio/Modelo/CasoPrueba.php',
        'if ($aprobador === $this->autor) {', 'if (false) {'],
    'M2 la campaña inicia sin los críticos de la matriz (R-03)' => ['src/Dominio/Modelo/Campana.php',
        "if (\$faltan !== []) {\n            throw new ReglaRechazada('ALCANCE_INCOMPLETO', \$faltan);", "if (false) {\n            throw new ReglaRechazada('ALCANCE_INCOMPLETO', \$faltan);"],
    'M3 una FALLA sin evidencia se acepta (R-05)' => ['src/Dominio/Modelo/SolicitudResultado.php',
        'if ($resultado->exigeEvidencia() && $evidencias === []) {', 'if (false) {'],
    'M4 la referencia externa cuenta como evidencia (R-05)' => ['src/Dominio/Modelo/SolicitudResultado.php',
        '$evidencias === []) {', '$evidencias === [] && ($datos[\'referencias\'] ?? []) === []) {'],
    'M5 almacén caído: se registra igual sin evidencia (fail-open)' => ['src/Aplicacion/CasosDeUso/RegistrarResultado.php',
        '$e->tamano(), $e->clasificacion()->value, $this->almacen->guardar($tenant, $e)), $solicitud->evidencias);',
        '$e->tamano(), $e->clasificacion()->value, (function () use ($tenant, $e) { try { return $this->almacen->guardar($tenant, $e); } catch (AlmacenNoDisponible) { return \'pendiente\'; } })()), $solicitud->evidencias);'],
    'M6 una FALLA sin defecto se acepta (R-06)' => ['src/Dominio/Modelo/SolicitudResultado.php',
        "?? throw new ReglaRechazada('DEFECTO_REQUERIDO');", "?? null; if (\$d === null) { return null; }"],
    'M7 cambiar la severidad no exige motivo (R-07)' => ['src/Dominio/Modelo/Defecto.php',
        'if ($severidad !== $this->propuesta && trim((string) $motivo) === \'\') {', 'if (false) {'],
    'M8 el desarrollador cierra el defecto (R-08)' => ['src/Dominio/Modelo/Defecto.php',
        '$this->estado = EstadoDefecto::CORREGIDO;', '$this->estado = EstadoDefecto::CERRADO;'],
    'M9 la decisión cuenta el PRIMER intento, no el último (R-09)' => ['src/Dominio/Modelo/Campana.php',
        "            if (\$i->casoId === \$casoId) {\n                \$ultimo = \$i;", "            if (\$i->casoId === \$casoId && \$ultimo === null) {\n                \$ultimo = \$i;"],
    'M10 GO aunque haya críticos abiertos (R-09)' => ['src/Dominio/Modelo/Campana.php',
        'if ($criticosAbiertos > 0) {', 'if (false) {'],
    'M11 sin idempotencia (R-12)' => ['src/Aplicacion/CasosDeUso/RegistrarResultado.php',
        'return $previo !== null ? $this->repetido($previo, $huella)', 'return false ? $this->repetido($previo, $huella)'],
    'M12 el resultado y la traza en transacciones separadas' => ['src/Aplicacion/CasosDeUso/RegistrarResultado.php',
        "            \$this->auditoria->registrar('RESULTADO_REGISTRADO'", "            try { \$this->auditoria->registrar('RESULTADO_REGISTRADO'"],
    'M13 la consulta de campañas no filtra por tenant (SQL)' => ['src/Persistencia/Pdo/PdoRepositorioCampanas.php',
        'FROM campanas WHERE tenant = ? AND id = ?\', [$tenant, $id]', 'FROM campanas WHERE (tenant = ? OR true) AND id = ?\', [$tenant, $id]'],
    'M14 los intentos se pueden modificar (esquema)' => ['database/schema.sql',
        'CREATE TRIGGER intentos_solo_agregar BEFORE UPDATE OR DELETE ON intentos', 'CREATE TRIGGER intentos_solo_agregar BEFORE TRUNCATE ON intentos FOR EACH STATEMENT EXECUTE FUNCTION solo_agregar();
-- '],
    'M15 la trazabilidad expone el contenido de la evidencia' => ['src/Aplicacion/CasosDeUso/ConsultarTrazabilidad.php',
        "'tamano' => \$e->tamano, 'clasificacion' => \$e->clasificacion]", "'tamano' => \$e->tamano, 'clasificacion' => \$e->clasificacion, 'ubicacion' => 'captura-de-prueba']"],
    'M16 la vista manda las reglas de datos como 409' => ['src/Presentacion/Http/Vistas/SalidaVista.php',
        'Salida::INVALIDO => 422,', 'Salida::INVALIDO => 409,'],
    // Semana 4: adaptadores InMemory, contrato del Repository y MVC.
    'M17 el repositorio en memoria no filtra campañas por tenant' => ['src/Persistencia/EnMemoria/RepositorioCampanasEnMemoria.php',
        'if ($c === null || $c->tenant !== $tenant) {', 'if ($c === null) {'],
    'M18 la transacción en memoria no deshace (sin ROLLBACK)' => ['src/Persistencia/EnMemoria/TransaccionEnMemoria.php',
        '$p->restaurar($estados[$i]);', '// sin restaurar'],
    'M19 PDO: un caso duplicado vuelve a dar 503 (contrato roto)' => ['src/Persistencia/Pdo/PdoRepositorioCasos.php',
        "throw new ReglaRechazada('CASO_EXISTENTE');   // mismo contrato", "null; // mismo contrato"],
    'M20 los requisitos dejan de ordenarse (contrato roto)' => ['src/Dominio/Modelo/CasoPrueba.php',
        "        sort(\$validos);\n", "\n"],
    'M21 el formulario acepta verifica_defecto como texto' => ['src/Presentacion/Http/Formularios/FormularioResultado.php',
        "'verifica_defecto' => 'entero'", "'verifica_defecto' => 'texto'"],
    'M22 el controlador llama al caso de uso aunque el formulario falle' => ['src/Presentacion/Http/Controladores/CampanasControlador.php',
        "        } catch (EntradaInvalida \$e) {\n            return \$this->error->entrada(\$e);\n        }\n\n        return \$this->vista->mostrar(\$this->registrar",
        "        } catch (EntradaInvalida \$e) {\n            \$clave = 'x'; \$datos = [];\n        }\n\n        return \$this->vista->mostrar(\$this->registrar"],
    // Semana 5: contrato API, cliente, almacén remoto y observabilidad.
    'M23 el contrato declara otro permiso para registrar resultados' => ['api/openapi.yaml',
        'x-permiso: qa.ejecutar', 'x-permiso: qa.consultar'],
    'M24 el contrato deja de documentar el 413 de registrarResultado' => ['api/openapi.yaml',
        "        '413':\n          \$ref: '#/components/responses/DemasiadoGrande'\n", ''],
    'M25 el cliente reintenta también los 4xx' => ['src/Cliente/ClienteQa.php',
        'if ($r[\'estado\'] < 500) {', 'if ($r[\'estado\'] < 400) {'],
    'M26 el cliente no guarda los pendientes (se pierden)' => ['src/Cliente/ClienteQa.php',
        '$this->bandeja->guardar($clave, $ruta, $cuerpo);', '// sin bandeja'],
    'M27 el almacén remoto acepta cualquier confirmación' => ['src/Persistencia/Http/AlmacenEvidenciasHttp.php',
        "|| (\$json['huella'] ?? null) !== \$evidencia->huella()) {", ') {'],
    'M28 el circuito nunca se abre' => ['src/Persistencia/Http/CircuitoInterruptor.php',
        '$this->abiertoDesde = $this->reloj->ahora();', '// nunca abre'],
    'M29 el registro de acceso guarda el cuerpo' => ['src/Presentacion/Http/Kernel.php',
        "'tenant' => \$identidad?->tenant,", "'tenant' => \$identidad?->tenant, 'cuerpo' => \$solicitud->cuerpo,"],
    // Semana 6: cambio práctico R-15.
    'M30 un MAYOR abierto en un caso crítico ya no bloquea el GO' => ['src/Dominio/Modelo/Campana.php',
        'if ($mayoresEnCriticos > 0) {', 'if (false) {'],
    'M31 se cuentan los MAYOR de TODO el alcance, no solo de los críticos' => ['src/Aplicacion/CasosDeUso/CerrarCampana.php',
        '$this->defectos->mayoresAbiertos($tenant, $criticosDeMatriz)', '$this->defectos->mayoresAbiertos($tenant, $casos)'],
    'M32 un MAYOR CORREGIDO (aún no verificado) deja de bloquear (SQL)' => ['src/Persistencia/Pdo/PdoRepositorioDefectos.php',
        "severidad = 'MAYOR' AND estado <> 'CERRADO'", "severidad = 'MAYOR' AND estado NOT IN ('CERRADO', 'CORREGIDO')"],
    'M33 la trazabilidad no marca los MAYOR que bloquean' => ['src/Aplicacion/CasosDeUso/ConsultarTrazabilidad.php',
        '\'bloquea_go\' => $d->criticoAbierto() ||', '\'bloquea_go\' => $d->criticoAbierto() || false &&'],
    // Semana 7: el refactor no debe cambiar el comportamiento observable (CAR-01).
    'M34 el tamaño se revisa DESPUÉS de construir la evidencia (cambia qué error sale)' => ['src/Dominio/Modelo/SolicitudResultado.php',
        "            if (strlen(\$bytes) > self::TAMANO_MAXIMO) {\n                throw new ReglaRechazada('EVIDENCIA_DEMASIADO_GRANDE');\n            }\n            \$clasificacion",
        "            \$clasificacion"],
    'M35 el defecto a verificar se busca DESPUÉS de las reglas del defecto nuevo' => ['src/Aplicacion/CasosDeUso/RegistrarResultado.php',
        "\$verificado = \$this->defectoAVerificar(\$tenant, \$casoId, \$datos['verifica_defecto'] ?? null);\n        \$nuevo = \$solicitud->resolverDefecto(\$verificado,",
        "\$nuevo = \$solicitud->resolverDefecto(null,"],
];

$origen = dirname(__DIR__);
$detectadas = 0;
foreach ($mutaciones as $nombre => [$archivo, $buscar, $reemplazo]) {
    $copia = sys_get_temp_dir() . '/micro-qa-mut-' . getmypid() . '-' . md5($nombre);
    copiar($origen, $copia);
    $codigo = str_replace("\r\n", "\n", (string) file_get_contents("$copia/$archivo"));
    if (!str_contains($codigo, $buscar)) {
        echo "[ERROR    ] $nombre: el texto a mutar no está en $archivo" . PHP_EOL;
        borrar($copia);
        continue;
    }
    $mutado = str_replace($buscar, $reemplazo, $codigo);
    if (str_starts_with($nombre, 'M12')) {
        // La traza se traga su propio error: el intento queda confirmado aunque la auditoría falle.
        $mutado = preg_replace('/(\$this->auditoria->registrar\(\'RESULTADO_REGISTRADO\'.*?\);)/', '$1 } catch (\Throwable) {}', $mutado, 1);
    }
    file_put_contents("$copia/$archivo", $mutado);
    exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=0 -d log_errors=0 ' . escapeshellarg("$copia/tests/ejecutar.php"), $salida, $codigoSalida);
    $fallan = array_values(array_filter($salida, static fn (string $l): bool => str_starts_with($l, '[FALLA]')));
    $ids = array_map(static fn (string $l): string => preg_split('/\s+/', $l)[1], $fallan);
    $detectada = $codigoSalida !== 0;
    $detectadas += $detectada ? 1 : 0;
    printf("[%s] %s %s\n", $detectada ? 'DETECTA  ' : 'SOBREVIVE', mb_str_pad($nombre, 64), $detectada ? 'falla: ' . implode(', ', $ids) : '');
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

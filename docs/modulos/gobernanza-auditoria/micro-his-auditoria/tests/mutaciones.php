<?php

declare(strict_types=1);

/*
 * Comprobación por mutación: introduce UN defecto a la vez en una copia temporal del
 * micro-HIS, ejecuta las pruebas y verifica que alguna falle. El código original no se toca.
 *   php tests/mutaciones.php
 * M1 y M11 solo las detecta PostgreSQL real: ejecútelo con AUDITORIA_DB_DSN definida (la heredan las copias).
 * Sale con 1 si alguna mutación sobrevive (las pruebas no la detectan) o no se pudo aplicar.
 */

$raiz = dirname(__DIR__);

$mutaciones = [
    'M1' => ['El detalle se sella sin canonizar (jsonb reordena las claves)', 'src/Dominio/Modelo/Movimiento.php',
        "'detalle' => self::canonico(\$this->detalle),", "'detalle' => \$this->detalle,"],
    'M2' => ['La consulta en memoria ignora el hospital (fuga entre tenants)', 'src/Dominio/Modelo/ConsultaMovimientos.php',
        'if (!$this->red && $m->hospital !== $this->hospital) {', 'if (false) {'],
    'M3' => ['El SQL de la consulta olvida el alcance del hospital', 'src/Persistencia/Pdo/PdoBitacora.php',
        "\$condiciones[] = 'hospital_id = :alcance_hospital';", ''],
    'M4' => ['«hasta» deja de incluir el día completo', 'src/Dominio/Filtros/FiltroRangoFechas.php',
        "\$this->fecha(\$p['hasta'])->modify('+1 day')", "\$this->fecha(\$p['hasta'])"],
    'M5' => ['Sin idempotencia: el reintento se registra otra vez', 'src/Aplicacion/CasosDeUso/RegistrarMovimiento.php',
        'if ($existente !== null) {', 'if (false) {'],
    'M6' => ['La consulta no se audita (R-08)', 'src/Aplicacion/CasosDeUso/ConsultarMovimientos.php',
        "\$this->cadena->auditarLectura(\$alcance->hospital,", "if (false) \$this->cadena->auditarLectura(\$alcance->hospital,"],
    'M7' => ['Registrar responde «creado» aunque la base falle', 'src/Aplicacion/CasosDeUso/RegistrarMovimiento.php',
        "        } catch (ErrorPersistencia) {\n            return Resultado::noDisponible();", "        } catch (ErrorPersistencia) {\n            return Resultado::creado([]);"],
    'M8' => ['La cadena no se bloquea antes de leer el último sello (carrera)', 'src/Aplicacion/Servicios/CadenaSellada.php',
        "        \$this->bitacora->bloquearCadena(\$movimiento->hospital);\n", ''],
    'M9' => ['Presentación importa una clase del Dominio (rompe la regla de capas)', 'src/Presentacion/Http/Controladores/AuditoriaControlador.php',
        "use Auditoria\\Presentacion\\Http\\Identidad;", "use Auditoria\\Presentacion\\Http\\Identidad;\nuse Auditoria\\Dominio\\Modelo\\Movimiento;"],
    'M10' => ['Un valor se concatena en el SQL (inyección)', 'src/Persistencia/Pdo/PdoBitacora.php',
        "return \$this->uno('SELECT ' . self::COLUMNAS . ' FROM audit_logs WHERE emisor = :emisor AND event_id = :event_id',\n            ['emisor' => \$emisor, 'event_id' => \$eventId]);",
        "return \$this->uno(\"SELECT \" . self::COLUMNAS . \" FROM audit_logs WHERE emisor = '\$emisor' AND event_id = :event_id\",\n            ['event_id' => \$eventId]);"],
    'M11' => ['El esquema pierde el trigger de solo inserción', 'database/schema.sql',
        "CREATE TRIGGER audit_logs_sin_update_delete BEFORE UPDATE OR DELETE ON audit_logs\n    FOR EACH ROW EXECUTE FUNCTION audit_logs_solo_insercion();\n", ''],
    'M12' => ['El kernel no compara el hospital del token con X-Tenant-ID', 'src/Presentacion/Http/Kernel.php',
        'if (!$identidad->puedeActuarEn($hospital)) {', 'if (false) {'],
    // Semana 4: MVC y Repository
    'M13' => ['El controlador arma la respuesta de error él mismo (salta la Vista)', 'src/Presentacion/Http/Controladores/AuditoriaControlador.php',
        "        } catch (EntradaInvalida \$e) {\n            return ErrorVista::entradaInvalida(\$e);\n        }\n    }\n\n    public function consultar",
        "        } catch (EntradaInvalida \$e) {\n            return Respuesta::json(400, ['error' => 'CUERPO_INVALIDO']);\n        }\n    }\n\n    public function consultar"],
    'M14' => ['El adaptador InMemory ignora el límite de la consulta', 'src/Persistencia/EnMemoria/BitacoraEnMemoria.php',
        'return array_slice($encontrados, 0, $consulta->limite);', 'return $encontrados;'],
    'M15' => ['El adaptador InMemory acepta el mismo (emisor, event_id) dos veces', 'src/Persistencia/EnMemoria/BitacoraEnMemoria.php',
        'if (($m->emisor === $movimiento->emisor && $m->eventId === $movimiento->eventId)', 'if ((false)'],
    // Semana 5: cliente-servidor, ancla y observabilidad
    'M16' => ['El cliente guarda en la bandeja también los 4xx (reintentaría para siempre un error del emisor)', 'src/Cliente/ClienteAuditoria.php',
        "        if (\$r->origen === ResultadoCliente::SERVIDOR) {\n            return \$r;\n        }",
        "        if (\$r->origen === ResultadoCliente::SERVIDOR && !\$r->rechazado()) {\n            return \$r;\n        }"],
    'M17' => ['La verificación ignora el ancla (no detecta el truncamiento)', 'src/Aplicacion/CasosDeUso/VerificarIntegridad.php',
        'if ($ancla !== null && $ancla[\'secuencia\'] > 0) {', 'if (false) {'],
    'M18' => ['El kernel no devuelve X-Request-ID', 'src/Presentacion/Http/Kernel.php',
        "\$respuesta = \$respuesta->conCabecera('X-Request-ID', \$requestId);", ''],
    // Semana 6: cambio práctico «corrección trazable»
    'M19' => ['Se acepta corregir dos veces el mismo original', 'src/Aplicacion/CasosDeUso/RegistrarMovimiento.php',
        'if ($this->bitacora->correccionDe((int) $original->id) !== null) {', 'if (false) {'],
    'M20' => ['Se puede corregir un movimiento de OTRO hospital (fuga entre tenants)', 'src/Aplicacion/CasosDeUso/RegistrarMovimiento.php',
        'if ($original !== null && $original->hospital !== $nuevo->hospital) {', 'if (false) {'],
    'M21' => ['La consulta deja de avisar que un original fue corregido', 'src/Aplicacion/Servicios/ProyeccionMovimientos.php',
        "['corregido_por' => \$corregidos[\$m->id] ?? null]", "['corregido_por' => null]"],
    // Semana 7: refactor de la consulta (vigilan las piezas extraídas)
    'M22' => ['El alcance de un hospital incluye movimientos de otro (fuga entre tenants en el detalle)', 'src/Dominio/Modelo/Alcance.php',
        'return $this->esRed() || $m->hospital === $this->hospital;', 'return true;'],
    'M23' => ['La lectura de red se autoriza con el permiso de hospital', 'src/Aplicacion/Servicios/AutorizacionLectura.php',
        '$alcance->esRed() ? self::PERMISO_RED : self::PERMISO', 'self::PERMISO'],
    'M24' => ['El catálogo acepta un parámetro que ningún filtro reclama (se ignoraría en silencio)', 'src/Dominio/Filtros/CatalogoFiltros.php',
        'if (!in_array($nombre, $reclamados, true)) {', 'if (false) {'],
    // Semana 12: cambio solicitado «filtrar por recurso»
    'M25' => ['El filtro por recurso compara contra otro campo (nunca encuentra la ficha)', 'src/Dominio/Filtros/FiltroRecurso.php',
        "return \$m->recurso ?? '';", 'return $m->usuario;'],
    'M26' => ['El filtro por recurso hereda el máximo de 64 (no se podría buscar un recurso que sí se registró)', 'src/Dominio/Filtros/FiltroRecurso.php',
        "        return 128;", "        return 64;"],
];

$sobreviven = 0;
foreach ($mutaciones as $id => [$descripcion, $archivo, $buscar, $reemplazo]) {
    $copia = sys_get_temp_dir() . '/micro-his-auditoria-mutacion-' . $id . '-' . bin2hex(random_bytes(3));
    copiar($raiz, $copia);
    $codigo = (string) file_get_contents("$copia/$archivo");
    $mutado = str_replace($buscar, $reemplazo, $codigo, $veces);
    if ($veces !== 1) {
        echo PHP_EOL . "$id - $descripcion" . PHP_EOL . '   NO SE PUDO APLICAR (el texto a reemplazar no aparece exactamente una vez)' . PHP_EOL;
        $sobreviven++;
        borrar($copia);
        continue;
    }
    file_put_contents("$copia/$archivo", $mutado);

    exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=0 -d log_errors=0 ' . escapeshellarg("$copia/tests/ejecutar.php"), $salida, $codigoSalida);
    $fallas = array_values(array_filter($salida, static fn (string $l): bool => str_starts_with($l, '[FALLA]')));
    echo PHP_EOL . "$id - $descripcion" . PHP_EOL;
    foreach ($fallas as $linea) {
        echo '   ' . mb_strimwidth($linea, 0, 120, '…') . PHP_EOL;
    }
    if ($fallas === []) {
        echo '   SOBREVIVE: ninguna prueba la detecta' . PHP_EOL;
        $sobreviven++;
    }
    $salida = [];
    borrar($copia);
}

echo PHP_EOL . sprintf('Mutaciones: %d  Detectadas: %d  Sobreviven: %d', count($mutaciones), count($mutaciones) - $sobreviven, $sobreviven) . PHP_EOL;
exit($sobreviven === 0 ? 0 : 1);

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

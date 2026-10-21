<?php

declare(strict_types=1);

/*
 * Comprobación por mutación: introduce UN defecto a la vez en una copia temporal del
 * micro-HIS, ejecuta las pruebas y verifica que alguna falle. El código original no se toca.
 *   php tests/mutaciones.php
 * Sale con 1 si alguna mutación sobrevive (las pruebas no la detectan) o no se pudo aplicar.
 */

$raiz = dirname(__DIR__);

$mutaciones = [
    'M1' => ['El UPDATE deja de comparar la versión (R-06)', 'src/Persistencia/Pdo/PdoRepositorioPacientes.php',
        ' AND version = :version', ''],
    'M2' => ['El hash del documento pasa a ser el número en claro (R-04)', 'src/Persistencia/Seguridad/ProteccionDocumentos.php',
        "return hash_hmac('sha256', \$documento->canonico(), \$this->claveHash);", 'return $documento->numero();'],
    'M3' => ['La política deja de bloquear el documento repetido (R-02)', 'src/Dominio/Servicios/PoliticaDuplicados.php',
        "            if (\$titular !== null) {\n                throw new ErrorDominio(ErrorDominio::CONFLICTO, 'PACIENTE_EXISTENTE'",
        "            if (false) {\n                throw new ErrorDominio(ErrorDominio::CONFLICTO, 'PACIENTE_EXISTENTE'"],
    'M4' => ['Registrar responde «creado» aunque la base falle', 'src/Aplicacion/CasosDeUso/RegistrarPaciente.php',
        "        } catch (ErrorPersistencia) {\n            return Resultado::noDisponible();",
        "        } catch (ErrorPersistencia) {\n            return Resultado::creado([]);"],
    'M5' => ['El controlador importa una clase del Dominio (rompe la regla de capas)', 'src/Presentacion/Http/Controladores/PacientesControlador.php',
        "use Pacientes\\Aplicacion\\CasosDeUso\\ActualizarPaciente;", "use Pacientes\\Aplicacion\\CasosDeUso\\ActualizarPaciente;\nuse Pacientes\\Dominio\\Modelo\\Paciente;"],
    'M6' => ['La vista traduce los datos inválidos a 400 en vez de 422', 'src/Presentacion/Http/Vistas/ErrorVista.php',
        "'invalido' => 422,", "'invalido' => 400,"],
    'M7' => ['La búsqueda deja de escapar los comodines del usuario', 'src/Persistencia/Pdo/PdoRepositorioPacientes.php',
        "strtr((string) \$criterio->nombre, ['!' => '!!', '%' => '!%', '_' => '!_'])", '(string) $criterio->nombre'],
    'M8' => ['El dato y su evento ya no se guardan en una transacción (R-09)', 'src/Aplicacion/CasosDeUso/ActualizarPaciente.php',
        '$guardado = $this->transaccion->ejecutar(function ()', '$guardado = (function ()'],
    'M9' => ['El adaptador InMemory deja de comparar la versión', 'src/Persistencia/EnMemoria/RepositorioPacientesEnMemoria.php',
        'if ($actual->version !== $versionEsperada) {', 'if (false) {'],
    'M10' => ['El controlador toma una decisión de negocio y crea el comando', 'src/Presentacion/Http/Controladores/PacientesControlador.php',
        "            \$comando = FormularioPaciente::registro(\$solicitud, \$identidad);\n",
        "            \$comando = FormularioPaciente::registro(\$solicitud, \$identidad);\n            if (\$comando->datos['tipo_documento'] === 'DPI') {\n                \$comando = new \\Pacientes\\Aplicacion\\Comandos\\ComandoRegistrar(\$comando->actor, \$comando->hospital, \$comando->datos);\n            }\n"],
    'M11' => ['El Kernel arma una respuesta sin pasar por las Vistas', 'src/Presentacion/Http/Kernel.php',
        'return ErrorVista::hospitalRequerido();', "return Respuesta::json(400, ['error' => 'HOSPITAL_REQUERIDO']);"],
    'M12' => ['El adaptador InMemory deja de exigir el documento único', 'src/Persistencia/EnMemoria/RepositorioPacientesEnMemoria.php',
        'if ($canonico === null) {', 'if (true) {'],
];
// M8: sin la transacción, el closure se invoca directamente.
$ajustes = ['M8' => ['            });' . "\n\n" . "            return Resultado::ok(['codigo'", '            })();' . "\n\n" . "            return Resultado::ok(['codigo'"]];

function copiar(string $origen, string $destino): void
{
    $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($origen, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterador as $item) {
        $ruta = $destino . substr($item->getPathname(), strlen($origen));
        $item->isDir() ? @mkdir($ruta, 0777, true) : copy($item->getPathname(), $ruta);
    }
}

function borrar(string $directorio): void
{
    $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directorio, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterador as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($directorio);
}

$sobreviven = 0;
echo 'Comprobacion por mutacion del Micro-HIS Pacientes (PHP ' . PHP_VERSION . ')' . PHP_EOL;
foreach ($mutaciones as $id => [$descripcion, $archivo, $buscar, $reemplazo]) {
    $copia = sys_get_temp_dir() . '/micro-his-mutacion-' . $id . '-' . bin2hex(random_bytes(3));
    copiar($raiz, $copia);
    $codigo = (string) file_get_contents("$copia/$archivo");
    $mutado = str_replace($buscar, $reemplazo, $codigo, $veces);
    if (isset($ajustes[$id])) {
        $mutado = str_replace($ajustes[$id][0], $ajustes[$id][1], $mutado, $vecesAjuste);
        $veces = min($veces, $vecesAjuste);
    }
    if ($veces !== 1) {
        echo PHP_EOL . "$id - $descripcion" . PHP_EOL . '   NO SE PUDO APLICAR (el texto a reemplazar no aparece exactamente una vez)' . PHP_EOL;
        $sobreviven++;
        borrar($copia);
        continue;
    }
    file_put_contents("$copia/$archivo", $mutado);

    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$copia/tests/ejecutar.php"), $salida, $codigoSalida);
    $fallas = array_values(array_filter($salida, static fn (string $l): bool => str_starts_with($l, '[FALLA]')));
    echo PHP_EOL . "$id - $descripcion" . PHP_EOL;
    foreach ($fallas as $linea) {
        echo '   ' . $linea . PHP_EOL;
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

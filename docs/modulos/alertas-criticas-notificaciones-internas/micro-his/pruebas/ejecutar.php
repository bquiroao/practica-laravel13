<?php
// Ejecutor propio de pruebas del Micro-HIS (sin PHPUnit).
// Uso (desde micro-his/): php pruebas/ejecutar.php [--evidencia=ruta.txt] [--filtro=texto]
declare(strict_types=1);

require __DIR__ . '/../autoload.php';

// Cualquier warning o notice cuenta como falla (no se esconde en la salida).
set_error_handler(function (int $nivel, string $mensaje, string $archivo, int $linea): bool {
    throw new ErrorException($mensaje, 0, $nivel, $archivo, $linea);
});

final class Pruebas
{
    private array $casos = [];

    public function prueba(string $nombre, callable $cuerpo): void
    {
        $this->casos[] = [$nombre, $cuerpo];
    }

    public function igual(mixed $esperado, mixed $real, string $msg = ''): void
    {
        if ($esperado !== $real) {
            throw new AssertionError(trim("$msg Se esperaba " . var_export($esperado, true) . ', se obtuvo ' . var_export($real, true)));
        }
    }

    public function verdadero(bool $condicion, string $msg = 'La condición no se cumple'): void
    {
        if (!$condicion) {
            throw new AssertionError($msg);
        }
    }

    /** Exige que $accion lance $clase; devuelve la excepción para revisarla. */
    public function lanza(string $clase, callable $accion): Throwable
    {
        try {
            $accion();
        } catch (Throwable $e) {
            if ($e instanceof $clase) {
                return $e;
            }
            throw new AssertionError("Se esperaba $clase y se lanzó " . $e::class . ': ' . $e->getMessage());
        }
        throw new AssertionError("Se esperaba $clase y no se lanzó nada");
    }

    public function casos(): array
    {
        $casos = $this->casos;
        $this->casos = [];
        return $casos;
    }
}

$opciones = getopt('', ['evidencia::', 'filtro::']);
$t = new Pruebas();
$lineas = ['Micro-HIS Alertas críticas y notificaciones internas — salida de pruebas',
           'PHP ' . PHP_VERSION . ' · ' . date('Y-m-d H:i:s'), ''];
$total = 0;
$fallos = 0;
$inicio = microtime(true);

$archivos = glob(__DIR__ . '/*/*-pruebas.php');
sort($archivos);
foreach ($archivos as $archivo) {
    // Cada archivo se carga en su propio ámbito para que sus variables no choquen.
    (static function (string $__archivo, Pruebas $t): void { require $__archivo; })($archivo, $t);
    $grupo = basename(dirname($archivo)) . '/' . basename($archivo);
    $lineas[] = "== $grupo";
    foreach ($t->casos() as [$nombre, $cuerpo]) {
        if (isset($opciones['filtro']) && !str_contains($nombre, (string) $opciones['filtro'])) {
            continue;
        }
        $total++;
        try {
            $cuerpo();
            $lineas[] = "  [OK]    $nombre";
        } catch (Throwable $e) {
            $fallos++;
            $lineas[] = "  [FALLA] $nombre";
            $lineas[] = '          ' . $e::class . ': ' . $e->getMessage();
        }
    }
}

$lineas[] = '';
$lineas[] = sprintf('Total: %d · Pasaron: %d · Fallaron: %d · Tiempo: %.2f s', $total, $total - $fallos, $fallos, microtime(true) - $inicio);
echo implode(PHP_EOL, $lineas), PHP_EOL;

if (!empty($opciones['evidencia'])) {
    $ruta = (string) $opciones['evidencia'];
    if (!is_dir(dirname($ruta))) {
        mkdir(dirname($ruta), 0777, true);
    }
    file_put_contents($ruta, implode(PHP_EOL, $lineas) . PHP_EOL); // UTF-8, no UTF-16 como el «>» de PowerShell
    echo "Evidencia escrita en $ruta", PHP_EOL;
}
exit($fallos === 0 ? 0 : 1);

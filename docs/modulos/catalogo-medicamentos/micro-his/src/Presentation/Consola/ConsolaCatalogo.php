<?php
declare(strict_types=1);

namespace Catalogo\Presentation\Consola;

use Catalogo\Application\MedicamentoVista;
use Catalogo\Domain\Errores\ErrorDominio;
use Catalogo\Domain\Errores\ErrorNoEncontrado;
use Catalogo\Domain\Errores\ErrorPersistencia;
use Catalogo\Domain\Errores\ErrorReglaNegocio;
use Catalogo\Domain\Errores\ErrorValidacion;

/**
 * Capa Presentation (consola). Solo traduce argumentos a llamadas de casos de uso y resultados a
 * texto. No tiene SQL ni reglas. En la semana 3 no hay autenticación: el hospital y el usuario se
 * indican con --hospital y --usuario (la API de la semana 5 los toma del token).
 */
final class ConsolaCatalogo
{
    private const HOSPITALES = ['norte' => '11111111-1111-4111-8111-111111111111', 'sur' => '22222222-2222-4222-8222-222222222222'];
    private const AYUDA = <<<TXT
    Uso: php bin/catalogo.php <orden> [argumentos] [--hospital=norte|sur] [--usuario=qf.norte]
      buscar <texto> [--forma=TABLETA] [--fecha=AAAA-MM-DD]
      ver <codigo> [--fecha=AAAA-MM-DD]
      historial <codigo>
      registrar --nombre=.. --principio_activo=.. --concentracion_valor=.. --concentracion_unidad=..
                --forma_farmaceutica=.. --via_administracion=.. --presentacion=.. [--registro_sanitario=..] [--vigente_desde=..]
      version <codigo> (mismos campos que registrar)
      baja <codigo> --motivo=.. [--fecha=AAAA-MM-DD]
      eliminar <codigo>
    TXT;

    /** @param array<string, object> $app casos de uso del Composition Root */
    public function __construct(private readonly array $app)
    {
    }

    /** @param list<string> $argv */
    public function ejecutar(array $argv): int
    {
        [$orden, $posicionales, $opciones] = self::leer($argv);
        $hospital = self::HOSPITALES[$opciones['hospital'] ?? 'norte'] ?? null;
        $usuario = $opciones['usuario'] ?? 'qf.norte';
        unset($opciones['hospital'], $opciones['usuario']);
        if ($orden === null || $hospital === null) {
            echo self::AYUDA . PHP_EOL;
            return $orden === null ? 0 : 2;
        }
        $codigo = $posicionales[0] ?? '';
        try {
            $hoy = $this->app['reloj']->hoy();
            match ($orden) {
                'buscar' => $this->tabla($this->app['buscar']->ejecutar($hospital, ['q' => $codigo] + $opciones), $hoy),
                'ver' => $this->tabla([$this->app['consultar']->ejecutar($hospital, $codigo, $opciones['fecha'] ?? null)], $hoy),
                'historial' => $this->tabla($this->app['historial']->ejecutar($hospital, $codigo), $hoy),
                'registrar' => $this->listo('Registrado', $this->app['registrar']->ejecutar($hospital, $opciones, $usuario)),
                'version' => $this->listo('Nueva versión', $this->app['nueva_version']->ejecutar($hospital, $codigo, $opciones, $usuario)),
                'baja' => $this->listo('Dado de baja', $this->app['baja']->ejecutar($hospital, $codigo, $opciones['fecha'] ?? null, $opciones['motivo'] ?? null)),
                'eliminar' => $this->app['eliminar']->ejecutar($hospital, $codigo) ?? print("Eliminado $codigo" . PHP_EOL),
                default => throw new \InvalidArgumentException("Orden desconocida: $orden"),
            };
            return 0;
        } catch (ErrorDominio $e) {
            $tipo = match (true) {
                $e instanceof ErrorValidacion => '422 datos inválidos',
                $e instanceof ErrorReglaNegocio => '409 ' . $e->codigo,
                $e instanceof ErrorNoEncontrado => '404 no encontrado',
                $e instanceof ErrorPersistencia => '503 sin acceso a la base',
                default => $e->codigo,
            };
            fwrite(STDERR, "[$tipo] " . $e->getMessage() . PHP_EOL);
            foreach ($e->detalles as $campo => $mensaje) {
                fwrite(STDERR, "  - $campo: $mensaje" . PHP_EOL);
            }
            return $e instanceof ErrorPersistencia ? 3 : 1;
        } catch (\InvalidArgumentException $e) {
            fwrite(STDERR, $e->getMessage() . PHP_EOL . self::AYUDA . PHP_EOL);
            return 2;
        }
    }

    private function tabla(array $lista, \DateTimeImmutable $hoy): void
    {
        if ($lista === []) {
            echo 'Sin resultados vigentes para ese criterio.' . PHP_EOL;
            return;
        }
        printf("%-10s %2s  %-42s %-12s %-10s %-10s %s%s", 'Código', 'v', 'Nombre', 'Forma', 'Desde', 'Hasta', 'Vigente hoy', PHP_EOL);
        foreach (MedicamentoVista::lista($lista, $hoy) as $m) {
            printf("%-10s %2d  %-42s %-12s %-10s %-10s %s%s", $m['codigo'], $m['version'], mb_strimwidth((string) $m['nombre'], 0, 42, '…'),
                substr((string) $m['forma_farmaceutica'], 0, 12), $m['vigente_desde'], $m['vigente_hasta'] ?? '(abierta)', $m['vigente_hoy'], PHP_EOL);
        }
    }

    private function listo(string $accion, object $m): void
    {
        $d = MedicamentoVista::aArreglo($m);
        printf('%s: %s versión %d, vigente desde %s%s%s', $accion, $d['codigo'], $d['version'], $d['vigente_desde'],
            $d['vigente_hasta'] !== null ? ' hasta ' . $d['vigente_hasta'] : '', PHP_EOL);
    }

    /** @return array{0: ?string, 1: list<string>, 2: array<string, string>} */
    private static function leer(array $argv): array
    {
        $orden = null;
        $posicionales = [];
        $opciones = [];
        foreach (array_slice($argv, 1) as $arg) {
            if (preg_match('/^--([a-z_]+)=(.*)$/s', $arg, $m) === 1) {
                $opciones[$m[1]] = $m[2];
            } elseif ($orden === null) {
                $orden = $arg;
            } else {
                $posicionales[] = $arg;
            }
        }
        return [$orden, $posicionales, $opciones];
    }
}

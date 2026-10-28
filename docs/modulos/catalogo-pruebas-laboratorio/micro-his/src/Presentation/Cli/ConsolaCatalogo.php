<?php
declare(strict_types=1);

namespace Lab\Presentation\Cli;

use Lab\Application\ConsultarPruebasVigentes;
use Lab\Application\RegistrarPrueba;
use Lab\Application\VerDetallePrueba;
use Lab\Domain\DatosInvalidos;
use Lab\Domain\LabTest;

/** Presentation de consola: lee argumentos, llama al caso de uso y muestra el resultado. */
final class ConsolaCatalogo
{
    public function __construct(
        private readonly RegistrarPrueba $registrar,
        private readonly ConsultarPruebasVigentes $consultar,
        private readonly VerDetallePrueba $detalle,
        private readonly string $tenantId,
    ) {
    }

    public function ejecutar(array $argv): int
    {
        [$comando, $a1, $a2] = [$argv[1] ?? '', $argv[2] ?? '', $argv[3] ?? ''];
        try {
            switch ($comando) {
                case 'alta':
                    $datos = json_decode((string) file_get_contents($a1), true, flags: JSON_THROW_ON_ERROR);
                    echo 'Registrada: ' . self::linea($this->registrar->ejecutar($datos, $this->tenantId, 'jefe.lab')) . PHP_EOL;
                    break;
                case 'vigentes':
                    $lista = $this->consultar->ejecutar($this->tenantId, ['texto' => $a1, 'fecha' => $a2]);
                    echo $lista ? implode(PHP_EOL, array_map([self::class, 'linea'], $lista)) . PHP_EOL : 'Sin pruebas vigentes.' . PHP_EOL;
                    break;
                case 'detalle':
                    echo self::linea($this->detalle->ejecutar($this->tenantId, $a1, $a2)) . PHP_EOL;
                    break;
                default:
                    echo 'Comandos: alta <archivo.json> | vigentes [texto] [fecha] | detalle <código> [fecha]' . PHP_EOL;
            }
            return 0;
        } catch (DatosInvalidos $e) {
            fwrite(STDERR, 'Datos inválidos:' . PHP_EOL . '  - ' . implode(PHP_EOL . '  - ', $e->errores) . PHP_EOL);
        } catch (\Throwable $e) {
            fwrite(STDERR, (new \ReflectionClass($e))->getShortName() . ': ' . $e->getMessage() . PHP_EOL);
        }
        return 1;
    }

    private static function linea(LabTest $p): string
    {
        return sprintf(
            '%s v%d | %s | %s | %s, %s, %.1f mL, ayuno %d h | vigente %s',
            $p->codigo, $p->version, $p->nombre, $p->seccion, $p->requisito->tipoMuestra,
            $p->requisito->contenedor, $p->requisito->volumenMinimoMl, $p->requisito->horasAyuno, $p->vigencia->texto(),
        );
    }
}

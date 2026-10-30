<?php
declare(strict_types=1);

namespace Catalogo\Application;

use Catalogo\Domain\Errores\ErrorValidacion;
use Catalogo\Domain\Modelo\CodigoMedicamento;
use Catalogo\Domain\Modelo\Fecha;
use Catalogo\Domain\Modelo\FichaMedicamento;

/** Reúne en un solo ErrorValidacion los errores de la ficha y de la fecha (RF-02). */
final class LecturaDatos
{
    /**
     * @param array<string, mixed> $datos
     * @return array{0: FichaMedicamento, 1: \DateTimeImmutable}
     */
    public static function fichaYFecha(array $datos, string $campoFecha, \DateTimeImmutable $porDefecto): array
    {
        [$ficha, $errores] = FichaMedicamento::validar($datos);
        $fecha = $porDefecto;
        if (isset($datos[$campoFecha]) && $datos[$campoFecha] !== '') {
            try {
                $fecha = Fecha::desdeTexto($campoFecha, $datos[$campoFecha]);
            } catch (ErrorValidacion $e) {
                $errores += $e->detalles;
            }
        }
        if ($errores !== []) {
            throw new ErrorValidacion($errores);
        }
        return [$ficha, $fecha];
    }

    public static function codigo(mixed $valor): CodigoMedicamento
    {
        return CodigoMedicamento::desdeTexto($valor);
    }
}

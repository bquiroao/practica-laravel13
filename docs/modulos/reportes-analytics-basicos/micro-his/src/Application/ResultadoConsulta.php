<?php
declare(strict_types=1);

namespace MicroHis\Application;

use MicroHis\Domain\Desglose;
use MicroHis\Domain\Indicador;
use MicroHis\Domain\RegistroOrigen;

/** Lo que el caso de uso entrega a la presentación: éxito, sin datos o error, con su código HTTP. */
final class ResultadoConsulta
{
    /** @param list<RegistroOrigen>|null $registros */
    private function __construct(
        public readonly string $estado,
        public readonly int $codigoHttp,
        public readonly string $mensaje,
        public readonly ?Indicador $indicador = null,
        public readonly ?Desglose $desglose = null,
        public readonly ?array $registros = null,
    ) {
    }

    public static function exito(Indicador $indicador, ?Desglose $desglose, ?array $registros): self
    {
        return new self('exito', 200, '', $indicador, $desglose, $registros);
    }

    public static function sinDatos(string $mensaje): self
    {
        return new self('sin_datos', 200, $mensaje);
    }

    public static function error(int $codigoHttp, string $mensaje): self
    {
        return new self('error', $codigoHttp, $mensaje);
    }
}

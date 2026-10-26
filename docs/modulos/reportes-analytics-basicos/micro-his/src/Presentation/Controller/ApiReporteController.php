<?php
declare(strict_types=1);

namespace MicroHis\Presentation\Controller;

use MicroHis\Application\CalcularIndicador;
use MicroHis\Application\ResultadoConsulta;
use MicroHis\Domain\Filtros;
use MicroHis\Presentation\Http\Respuesta;
use MicroHis\Presentation\View\JsonIndicador;

/** Servidor del diseño cliente-servidor: expone el caso de uso como API REST v1 (JSON). Sin SQL ni reglas. */
final class ApiReporteController
{
    public function __construct(private readonly CalcularIndicador $casoDeUso, private readonly JsonIndicador $vista)
    {
    }

    public function indicador(array $peticion): Respuesta
    {
        return $this->responder($peticion, 'indicador');
    }

    public function desglose(array $peticion): Respuesta
    {
        return $this->responder($peticion, 'desglose');
    }

    public function registros(array $peticion): Respuesta
    {
        return $this->responder($peticion, 'registros');
    }

    private function responder(array $p, string $nivel): Respuesta
    {
        $correlacion = $p['correlacion'] ?? 'sin-id';
        try {
            $filtros = new Filtros($p['desde'] ?? '', $p['hasta'] ?? '');
            $resultado = $this->casoDeUso->ejecutar($p['rol'] ?? '', $p['tipo'], $filtros, $nivel, $p['dimension'] ?? null, $p['parte'] ?? null);
        } catch (\InvalidArgumentException $e) {
            $resultado = ResultadoConsulta::error(422, $e->getMessage());
        }
        $cuerpo = json_encode($this->vista->datos($resultado, $correlacion), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_PRETTY_PRINT);
        return new Respuesta($resultado->codigoHttp, $cuerpo, [
            'Content-Type' => 'application/json; charset=utf-8',
            'X-Correlation-Id' => $correlacion,
        ]);
    }
}

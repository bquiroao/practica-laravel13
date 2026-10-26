<?php
declare(strict_types=1);

namespace MicroHis\Catalogo\Presentacion;

use MicroHis\Catalogo\Aplicacion\{ErrorPersistencia, ServicioCatalogo};
use MicroHis\Catalogo\Dominio\ReglaDeDominio;

/** Recibe la petición, llama al caso de uso y traduce el resultado a HTTP. Sin SQL ni reglas de negocio. */
final class ControladorCatalogo
{
    private const HTTP = ['E2' => 422, 'E3' => 409, 'E4' => 422, 'E5' => 409, 'E6' => 422, 'E7' => 409, 'NF' => 404];

    public function __construct(private ServicioCatalogo $servicio) {}

    public function atender(string $accion, array $d): array
    {
        try {
            $exito = match ($accion) {
                'registrarSala' => [$this->servicio->registrarSala($d['codigo'] ?? '', $d['nombre'] ?? ''), 201],
                'registrarCama' => [$this->servicio->registrarCama($d['sala'] ?? '', $d['codigo'] ?? ''), 201],
                'cambiarEstado' => [$this->servicio->cambiarEstado($d['sala'] ?? '', $d['cama'] ?? '', $d['estado'] ?? '', $d['motivo'] ?? ''), 200],
                default => [null, 404],
            };
            return ['status' => $exito[1]];
        } catch (ReglaDeDominio $e) { return ['status' => self::HTTP[$e->codigo], 'error' => $e->codigo, 'mensaje' => $e->getMessage()];
        } catch (ErrorPersistencia) { return ['status' => 503, 'error' => 'E8', 'mensaje' => 'El catálogo no está disponible. Intenta más tarde.']; }
    }
}

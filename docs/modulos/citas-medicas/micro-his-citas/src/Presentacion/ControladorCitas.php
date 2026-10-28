<?php
declare(strict_types=1);

namespace MicroHis\Citas\Presentacion;

use MicroHis\Citas\Aplicacion\{ErrorPersistencia, ServicioCitas};
use MicroHis\Citas\Dominio\ReglaDeDominio;

/** Recibe la petición, llama al caso de uso y traduce el resultado a HTTP. Sin SQL ni reglas de negocio. */
final class ControladorCitas
{
    private const HTTP = ['E2' => 422, 'E3' => 422, 'E4' => 409, 'E5' => 409, 'E7' => 422, 'NF' => 404];

    public function __construct(private ServicioCitas $servicio) {}

    public function atender(string $accion, array $d): array
    {
        try {
            switch ($accion) {
                case 'solicitar':
                    return ['status' => 201, 'id' => $this->servicio->solicitar($d['medico'] ?? '', $d['paciente'] ?? '', $d['especialidad'] ?? '',
                                                                               (int) ($d['inicio'] ?? 0), (int) ($d['fin'] ?? 0), $d['motivo'] ?? '')];
                case 'confirmar': $this->servicio->confirmar((int) ($d['id'] ?? 0)); return ['status' => 200];
                case 'cancelar': $this->servicio->cancelar((int) ($d['id'] ?? 0), $d['motivo'] ?? ''); return ['status' => 200];
                default: return ['status' => 404];
            }
        } catch (ReglaDeDominio $e) { return ['status' => self::HTTP[$e->codigo], 'error' => $e->codigo, 'mensaje' => $e->getMessage()];
        } catch (ErrorPersistencia) { return ['status' => 503, 'error' => 'E8', 'mensaje' => 'La agenda no está disponible. Intenta más tarde.']; }
    }
}

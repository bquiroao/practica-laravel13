<?php
declare(strict_types=1);

namespace MicroHis\Expediente\Presentacion;

use MicroHis\Expediente\Aplicacion\{ErrorPersistencia, ServicioExpediente};
use MicroHis\Expediente\Dominio\ReglaDeDominio;

/** Recibe la petición, llama al caso de uso y traduce el resultado a HTTP. Sin SQL ni reglas de negocio.
 *  'profesional' vendrá de la sesión; en este Micro-HIS llega en la petición. */
final class ControladorExpediente
{
    private const HTTP = ['E2' => 422, 'E3' => 409, 'E4' => 403, 'E5' => 403, 'NF' => 404];
    public function __construct(private ServicioExpediente $servicio) {}

    public function atender(string $accion, array $d): array
    {
        $paciente = (string) ($d['paciente'] ?? ''); $profesional = (string) ($d['profesional'] ?? '');
        try {
            switch ($accion) {
                case 'abrir': return ['status' => 201, 'expediente' => $this->servicio->abrir($paciente, $profesional)];
                case 'consultar': return ['status' => 200] + $this->servicio->consultar($paciente, $profesional, (bool) ($d['longitudinal'] ?? false));
                default: return ['status' => 404];
            }
        } catch (ReglaDeDominio $e) { return ['status' => self::HTTP[$e->codigo], 'error' => $e->codigo, 'mensaje' => $e->getMessage()];
        } catch (ErrorPersistencia) { return ['status' => 503, 'error' => 'E8', 'mensaje' => 'El expediente no está disponible. Intenta más tarde.']; }
    }
}

<?php
declare(strict_types=1);

namespace MicroHis\Expediente\Presentacion;

use MicroHis\Expediente\Aplicacion\{ErrorPersistencia, ServicioExpediente};
use MicroHis\Expediente\Dominio\ReglaDeDominio;

/** Controlador (MVC): elige el caso de uso del modelo según la acción y entrega el resultado a la vista. Sin SQL ni reglas de negocio.
 *  'profesional' vendrá de la sesión; en este Micro-HIS llega en la petición. */
final class ControladorExpediente
{
    public function __construct(private ServicioExpediente $servicio, private VistaJson $vista) {}

    public function atender(string $accion, array $d): array
    {
        $paciente = (string) ($d['paciente'] ?? ''); $profesional = (string) ($d['profesional'] ?? '');
        try {
            switch ($accion) {
                case 'abrir': return $this->vista->abierto($this->servicio->abrir($paciente, $profesional));
                case 'consultar': return $this->vista->consulta($this->servicio->consultar($paciente, $profesional, (bool) ($d['longitudinal'] ?? false)));
                default: return $this->vista->rutaDesconocida();
            }
        } catch (ReglaDeDominio $e) { return $this->vista->rechazo($e);
        } catch (ErrorPersistencia) { return $this->vista->noDisponible(); }
    }
}

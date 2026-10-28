<?php
declare(strict_types=1);

namespace MicroHis\Citas\Presentacion;

use MicroHis\Citas\Aplicacion\{ErrorPersistencia, ServicioCitas};
use MicroHis\Citas\Dominio\ReglaDeDominio;

/** Controlador (MVC): elige el caso de uso del modelo según la acción y entrega el resultado a la vista. Sin SQL ni reglas de negocio. */
final class ControladorCitas
{
    public function __construct(private ServicioCitas $servicio, private VistaJson $vista) {}

    public function atender(string $accion, array $d): array
    {
        try {
            switch ($accion) {
                case 'solicitar':
                    return $this->vista->exito(201, ['id' => $this->servicio->solicitar($d['medico'] ?? '', $d['paciente'] ?? '', $d['especialidad'] ?? '',
                                                                                         (int) ($d['inicio'] ?? 0), (int) ($d['fin'] ?? 0), $d['motivo'] ?? '')]);
                case 'confirmar': $this->servicio->confirmar((int) ($d['id'] ?? 0)); return $this->vista->exito(200);
                case 'cancelar': $this->servicio->cancelar((int) ($d['id'] ?? 0), $d['motivo'] ?? ''); return $this->vista->exito(200);
                default: return $this->vista->rutaDesconocida();
            }
        } catch (ReglaDeDominio $e) { return $this->vista->rechazo($e);
        } catch (ErrorPersistencia) { return $this->vista->noDisponible(); }
    }
}

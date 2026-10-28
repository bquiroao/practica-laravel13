<?php
declare(strict_types=1);

namespace MicroHis\Catalogo\Presentacion;

use MicroHis\Catalogo\Aplicacion\{ErrorPersistencia, ServicioCatalogo};
use MicroHis\Catalogo\Dominio\ReglaDeDominio;

/** Controlador (MVC): elige el caso de uso del modelo según la acción y entrega el resultado a la vista. Sin SQL ni reglas de negocio. */
final class ControladorCatalogo
{
    public function __construct(private ServicioCatalogo $servicio, private VistaJson $vista) {}

    public function atender(string $accion, array $d): array
    {
        try {
            switch ($accion) {
                case 'registrarSala': $this->servicio->registrarSala($d['codigo'] ?? '', $d['nombre'] ?? ''); return $this->vista->exito(201);
                case 'registrarCama': $this->servicio->registrarCama($d['sala'] ?? '', $d['codigo'] ?? ''); return $this->vista->exito(201);
                case 'cambiarEstado': $this->servicio->cambiarEstado($d['sala'] ?? '', $d['cama'] ?? '', $d['estado'] ?? '', $d['motivo'] ?? ''); return $this->vista->exito(200);
                default: return $this->vista->rutaDesconocida();
            }
        } catch (ReglaDeDominio $e) { return $this->vista->rechazo($e);
        } catch (ErrorPersistencia) { return $this->vista->noDisponible(); }
    }
}

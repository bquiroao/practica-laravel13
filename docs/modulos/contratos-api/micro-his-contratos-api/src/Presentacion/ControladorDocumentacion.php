<?php
declare(strict_types=1);

namespace MicroHis\ContratosApi\Presentacion;

use MicroHis\ContratosApi\Aplicacion\{ErrorPersistencia, ServicioConsulta, ServicioPublicacion, ServicioRevision};
use MicroHis\ContratosApi\Dominio\ReglaDeDominio;

/** Controlador (MVC): elige el caso de uso del modelo según la acción y entrega el resultado a la vista. Sin SQL ni reglas de negocio.
 *  'usuario' y 'roles' vendrán de la sesión y de RBAC; en este Micro-HIS llegan en la petición. */
final class ControladorDocumentacion
{
    public function __construct(private ServicioPublicacion $publicacion, private ServicioRevision $revision, private ServicioConsulta $consulta, private VistaJson $vista) {}
    public function atender(string $accion, array $d): array
    {
        $id = (int) ($d['id'] ?? 0); $usuario = (string) ($d['usuario'] ?? '');
        try {
            switch ($accion) {
                case 'enviar': return $this->vista->exito(201, ['id' => $this->publicacion->enviar($d['slug'] ?? '', $d['tipo'] ?? '', $d['version'] ?? '', (array) ($d['audiencia'] ?? []), $usuario)]);
                case 'aprobar': $this->revision->aprobar($id, $usuario); return $this->vista->exito(200);
                case 'rechazar': $this->revision->rechazar($id, $usuario, $d['motivo'] ?? ''); return $this->vista->exito(200);
                case 'obsoleta': $this->revision->marcarObsoleta($id, $usuario); return $this->vista->exito(200);
                case 'consultar': return $this->vista->exito(200, $this->consulta->consultar($d['slug'] ?? '', $d['version'] ?? null, (array) ($d['roles'] ?? [])));
                default: return $this->vista->rutaDesconocida();
            }
        } catch (ReglaDeDominio $e) { return $this->vista->rechazo($e);
        } catch (ErrorPersistencia) { return $this->vista->noDisponible(); }
    }
}

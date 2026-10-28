<?php
declare(strict_types=1);

namespace MicroHis\ContratosApi\Presentacion;

use MicroHis\ContratosApi\Aplicacion\{ErrorPersistencia, ServicioConsulta, ServicioPublicacion, ServicioRevision};
use MicroHis\ContratosApi\Dominio\ReglaDeDominio;

/** Recibe la petición, llama al caso de uso y traduce el resultado a HTTP. Sin SQL ni reglas de negocio.
 *  'usuario' y 'roles' vendrán de la sesión y de RBAC; en este Micro-HIS llegan en la petición. */
final class ControladorDocumentacion
{
    private const HTTP = ['E2' => 422, 'E3' => 409, 'E5' => 403, 'E6' => 404, 'E7' => 409, 'NF' => 404];
    public function __construct(private ServicioPublicacion $publicacion, private ServicioRevision $revision, private ServicioConsulta $consulta) {}
    public function atender(string $accion, array $d): array
    {
        $id = (int) ($d['id'] ?? 0); $usuario = (string) ($d['usuario'] ?? '');
        try {
            switch ($accion) {
                case 'enviar': return ['status' => 201, 'id' => $this->publicacion->enviar($d['slug'] ?? '', $d['tipo'] ?? '', $d['version'] ?? '', (array) ($d['audiencia'] ?? []), $usuario)];
                case 'aprobar': $this->revision->aprobar($id, $usuario); return ['status' => 200];
                case 'rechazar': $this->revision->rechazar($id, $usuario, $d['motivo'] ?? ''); return ['status' => 200];
                case 'obsoleta': $this->revision->marcarObsoleta($id, $usuario); return ['status' => 200];
                case 'consultar': return ['status' => 200] + $this->consulta->consultar($d['slug'] ?? '', $d['version'] ?? null, (array) ($d['roles'] ?? []));
                default: return ['status' => 404];
            }
        } catch (ReglaDeDominio $e) { return ['status' => self::HTTP[$e->codigo], 'error' => $e->codigo, 'mensaje' => $e->getMessage()];
        } catch (ErrorPersistencia) { return ['status' => 503, 'error' => 'E8', 'mensaje' => 'El centro de documentación no está disponible. Intenta más tarde.']; }
    }
}

<?php
declare(strict_types=1);

namespace Admision\Application;

use Admision\Domain\Excepciones\AdmisionNoEncontrada;
use Admision\Domain\Modelo\EventoAdmision;
use Admision\Domain\Modelo\Rol;
use Admision\Domain\Repositorio\AdmisionRepository;

/** CU-06 (parte 2) / RF-08: eventos de una admisión; solo SUPERVISOR_CAMAS. */
final class ConsultarTrazabilidad
{
    public function __construct(private readonly AdmisionRepository $admisiones)
    {
    }

    /** @return list<array{tipo: string, usuario: string, rol: string, solicitud_id: string, ocurrido_en: string}> */
    public function ejecutar(string $admisionId, ContextoUsuario $u): array
    {
        $u->exigirRol(Rol::SUPERVISOR_CAMAS);
        if ($this->admisiones->buscarPorId($u->tenantId, $admisionId) === null) {
            throw new AdmisionNoEncontrada();
        }
        return array_map(fn (EventoAdmision $e) => [
            'tipo' => $e->tipo->value,
            'usuario' => $e->responsable->usuarioId,
            'rol' => $e->responsable->rol->value,
            'solicitud_id' => $e->responsable->solicitudId,
            'ocurrido_en' => $e->ocurridoEn->format(\DateTimeInterface::ATOM),
        ], $this->admisiones->eventos($u->tenantId, $admisionId));
    }
}

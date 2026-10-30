<?php
declare(strict_types=1);

namespace Admision\Application;

use Admision\Domain\Excepciones\AdmisionNoEncontrada;
use Admision\Domain\Modelo\Rol;
use Admision\Domain\Repositorio\AdmisionRepository;

/** CU-06 (parte 1): consultar una admisión del propio hospital. El médico solo consulta (RN-06). */
final class ConsultarAdmision
{
    public function __construct(private readonly AdmisionRepository $admisiones)
    {
    }

    public function ejecutar(string $admisionId, ContextoUsuario $u): ResultadoAdmision
    {
        $u->exigirRol(Rol::ADMISIONISTA, Rol::SUPERVISOR_CAMAS, Rol::MEDICO);
        $admision = $this->admisiones->buscarPorId($u->tenantId, $admisionId) ?? throw new AdmisionNoEncontrada();
        return ResultadoAdmision::desde($admision);
    }
}

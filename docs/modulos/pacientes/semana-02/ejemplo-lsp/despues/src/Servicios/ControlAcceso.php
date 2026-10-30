<?php

declare(strict_types=1);

namespace Pacientes\Servicios;

use Pacientes\Contratos\PoliticaAcceso;
use Pacientes\Dominio\ErrorPaciente;

/** Hospital obligatorio y permiso por operación (R-05), común a los tres servicios. */
final class ControlAcceso
{
    public function __construct(private readonly PoliticaAcceso $politica)
    {
    }

    /** @throws ErrorPaciente 400 HOSPITAL_REQUERIDO o 403 ACCESO_DENEGADO */
    public function exigir(string $actor, string $hospital, string $permiso): void
    {
        if (trim($hospital) === '') {
            throw new ErrorPaciente(400, 'HOSPITAL_REQUERIDO');
        }
        if (!$this->politica->permite($actor, $hospital, $permiso)) {
            throw new ErrorPaciente(403, 'ACCESO_DENEGADO');
        }
    }

    public function tiene(string $actor, string $hospital, string $permiso): bool
    {
        return $this->politica->permite($actor, $hospital, $permiso);
    }
}

<?php
declare(strict_types=1);

namespace Admision\Domain\Repositorio;

use Admision\Domain\Modelo\CodigoPaciente;
use Admision\Domain\Modelo\TenantId;

/** Puerto de SOLO LECTURA hacia el módulo Pacientes. Solo responde si el código existe y está activo. */
interface DirectorioPacientes
{
    public function existeActivo(TenantId $tenantId, CodigoPaciente $paciente): bool;
}

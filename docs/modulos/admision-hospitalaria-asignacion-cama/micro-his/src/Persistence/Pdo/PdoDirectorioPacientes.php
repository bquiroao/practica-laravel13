<?php
declare(strict_types=1);

namespace Admision\Persistence\Pdo;

use Admision\Domain\Excepciones\ErrorPersistencia;
use Admision\Domain\Modelo\CodigoPaciente;
use Admision\Domain\Modelo\TenantId;
use Admision\Domain\Repositorio\DirectorioPacientes;

/** Lectura de paciente_ref, que simula al módulo Pacientes. Solo código y estado activo. */
final class PdoDirectorioPacientes implements DirectorioPacientes
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function existeActivo(TenantId $tenantId, CodigoPaciente $paciente): bool
    {
        try {
            $sentencia = $this->pdo->prepare('SELECT activo FROM paciente_ref WHERE tenant_id = :tenant AND codigo_paciente = :codigo');
            $sentencia->execute([':tenant' => $tenantId->valor, ':codigo' => $paciente->valor]);
            return $sentencia->fetchColumn() === true;
        } catch (\PDOException $e) {
            throw new ErrorPersistencia('No se pudo consultar el paciente.', 0, $e);
        }
    }
}

<?php

declare(strict_types=1);

namespace Pacientes\Adaptadores;

use Pacientes\Contratos\RepositorioPacientes;
use Pacientes\Dominio\CriterioBusqueda;
use Pacientes\Dominio\Paciente;

/**
 * Caché de lecturas por código, como DECORADOR de cualquier RepositorioPacientes.
 *
 * Respeta el contrato porque no reimplementa la escritura: la delega al repositorio
 * envuelto, que compara la versión, y después invalida su entrada. Invalidar (en vez
 * de guardar el resultado) evita servir un dato que una transacción revertida nunca confirmó.
 */
final class RepositorioPacientesConCache implements RepositorioPacientes
{
    /** @var array<string, Paciente> */
    private array $cache = [];

    public function __construct(private readonly RepositorioPacientes $origen)
    {
    }

    public function porCodigo(string $hospital, string $codigo): ?Paciente
    {
        $llave = $hospital . '|' . $codigo;
        if (!isset($this->cache[$llave])) {
            $paciente = $this->origen->porCodigo($hospital, $codigo);
            if ($paciente === null) {
                return null;
            }
            $this->cache[$llave] = $paciente;
        }

        return $this->cache[$llave];
    }

    public function buscar(string $hospital, CriterioBusqueda $criterio): array
    {
        return $this->origen->buscar($hospital, $criterio);
    }

    public function registrar(Paciente $paciente): Paciente
    {
        return $this->origen->registrar($paciente);
    }

    public function actualizar(Paciente $cambios, int $versionEsperada): Paciente
    {
        unset($this->cache[$cambios->hospital . '|' . $cambios->codigo]);

        return $this->origen->actualizar($cambios, $versionEsperada);
    }
}

<?php

declare(strict_types=1);

namespace Pacientes\Antes;

use Pacientes\Dominio\Paciente;

/**
 * DISEÑO ANTES — violación V3 (postcondición debilitada).
 *
 * Para ahorrar una consulta, la subclase con caché sobrescribe actualizar() y escribe
 * directamente, sin comparar la versión esperada con la guardada. Cumple la firma, pero
 * ya no garantiza lo que promete la base: «si la versión no es la vigente, 409 y sin
 * cambios». Resultado: la segunda edición concurrente borra en silencio la primera.
 */
final class RepositorioPacientesConCache extends RepositorioPacientes
{
    /** @var array<string, Paciente> */
    private array $cache = [];

    public function porCodigo(string $hospital, string $codigo): ?Paciente
    {
        return $this->cache[$hospital . '|' . $codigo] ??= parent::porCodigo($hospital, $codigo);
    }

    public function actualizar(Paciente $cambios, int $versionEsperada): Paciente
    {
        // «La caché ya tiene la última versión»: se escribe sin comparar $versionEsperada.
        $guardado = $cambios->conVersion($cambios->version + 1);
        $this->pacientes[(string) $cambios->codigo] = $guardado;
        $this->cache[$cambios->hospital . '|' . $cambios->codigo] = $guardado;

        return $guardado;
    }
}

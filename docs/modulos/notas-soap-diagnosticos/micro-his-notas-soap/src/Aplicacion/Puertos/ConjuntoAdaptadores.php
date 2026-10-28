<?php

declare(strict_types=1);

namespace NotasSoap\Aplicacion\Puertos;

use NotasSoap\Dominio\Puertos\RegistroAuditoria;
use NotasSoap\Dominio\Puertos\RepositorioDiagnosticos;
use NotasSoap\Dominio\Puertos\RepositorioExpedientes;
use NotasSoap\Dominio\Puertos\RepositorioNotas;
use NotasSoap\Dominio\Puertos\VerificadorAcceso;

/**
 * Los puertos que necesitan los casos de uso, juntos. La raíz de composición arma uno con PDO
 * o uno en memoria; los casos de uso no saben cuál recibieron.
 */
final class ConjuntoAdaptadores
{
    public function __construct(
        public readonly VerificadorAcceso $acceso,
        public readonly RepositorioExpedientes $expedientes,
        public readonly RepositorioNotas $notas,
        public readonly RepositorioDiagnosticos $diagnosticos,
        public readonly RegistroAuditoria $auditoria,
        public readonly Transaccion $transaccion,
        public readonly RegistroIdempotencia $idempotencia,
    ) {
    }

    /** Copia con otro repositorio de notas (por ejemplo, envuelto en el decorador de eventos). */
    public function conNotas(RepositorioNotas $notas): self
    {
        return new self($this->acceso, $this->expedientes, $notas, $this->diagnosticos, $this->auditoria, $this->transaccion, $this->idempotencia);
    }

    public function conDiagnosticos(RepositorioDiagnosticos $diagnosticos): self
    {
        return new self($this->acceso, $this->expedientes, $this->notas, $diagnosticos, $this->auditoria, $this->transaccion, $this->idempotencia);
    }

    public function conAcceso(VerificadorAcceso $acceso): self
    {
        return new self($acceso, $this->expedientes, $this->notas, $this->diagnosticos, $this->auditoria, $this->transaccion, $this->idempotencia);
    }
}

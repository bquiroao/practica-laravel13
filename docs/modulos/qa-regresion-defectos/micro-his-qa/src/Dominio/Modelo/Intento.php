<?php

declare(strict_types=1);

namespace Qa\Dominio\Modelo;

use Qa\Dominio\Evidencia\ReferenciaExterna;

/**
 * Un resultado de un caso en una campaña (R-11): inmutable y sin métodos que lo cambien. Un error se corrige
 * registrando OTRO intento con motivo; la base rechaza UPDATE y DELETE con un trigger.
 */
final readonly class Intento
{
    /**
     * @param list<EvidenciaRegistrada> $evidencias
     * @param list<ReferenciaExterna>   $referencias
     */
    public function __construct(
        public ?int $id,
        public int $campanaId,
        public string $casoId,
        public int $numero,
        public Resultado $resultado,
        public string $tester,
        public array $evidencias,
        public array $referencias,
        public ?int $defectoId,
        public ?int $verificaDefecto,
        public ?string $motivo,
        public string $clave,
        public string $huellaSolicitud,
        public \DateTimeImmutable $registradoEn,
    ) {
    }

    public function conId(int $id): self
    {
        return new self($id, $this->campanaId, $this->casoId, $this->numero, $this->resultado, $this->tester, $this->evidencias,
            $this->referencias, $this->defectoId, $this->verificaDefecto, $this->motivo, $this->clave, $this->huellaSolicitud, $this->registradoEn);
    }
}

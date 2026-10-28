<?php
declare(strict_types=1);

namespace MicroHis\Domain;

/** Única precondición común de todos los reportes (RN-02). Value Object inmutable. */
final class Filtros
{
    public function __construct(
        public readonly string $desde,
        public readonly string $hasta,
        public readonly ?string $medicoId = null,
    ) {
        foreach ([$desde, $hasta] as $fecha) {
            $leida = \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
            if ($leida === false || $leida->format('Y-m-d') !== $fecha) {
                throw new \InvalidArgumentException("Fecha inválida «{$fecha}»: use el formato AAAA-MM-DD (RN-02)");
            }
        }
        if ($desde > $hasta) {
            throw new \InvalidArgumentException('La fecha inicial no puede ser mayor que la final (RN-02)');
        }
    }

    public function periodo(): string
    {
        return "{$this->desde} a {$this->hasta}";
    }
}

<?php
declare(strict_types=1);

namespace Admision\Domain\Modelo;

use Admision\Domain\Excepciones\DatoInvalido;

/** Hospital al que pertenecen los datos. Formato: HOSP-NN. */
final class TenantId
{
    private function __construct(public readonly string $valor)
    {
    }

    public static function desde(string $valor): self
    {
        if (preg_match('/^HOSP-[0-9]{2}\z/', $valor) !== 1) {
            throw new DatoInvalido('Hospital inválido.');
        }
        return new self($valor);
    }

    public function esIgualA(self $otro): bool
    {
        return $this->valor === $otro->valor;
    }
}

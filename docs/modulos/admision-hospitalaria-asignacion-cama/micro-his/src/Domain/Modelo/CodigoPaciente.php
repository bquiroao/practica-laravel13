<?php
declare(strict_types=1);

namespace Admision\Domain\Modelo;

use Admision\Domain\Excepciones\DatoInvalido;

/** El paciente se identifica solo por código (RNF-03). Formato: PAC-NNNNN. */
final class CodigoPaciente
{
    private function __construct(public readonly string $valor)
    {
    }

    public static function desde(string $valor): self
    {
        if (preg_match('/^PAC-[0-9]{5}\z/', $valor) !== 1) {
            throw new DatoInvalido('El código de paciente debe tener el formato PAC-NNNNN.');
        }
        return new self($valor);
    }
}

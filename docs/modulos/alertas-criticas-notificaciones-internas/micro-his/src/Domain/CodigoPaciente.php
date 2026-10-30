<?php
declare(strict_types=1);

namespace AlertasCriticas\Domain;

/** Objeto de valor: solo el código ficticio del paciente, nunca su nombre (RNF-02). */
final class CodigoPaciente
{
    public readonly string $valor;

    public function __construct(string $valor)
    {
        $valor = strtoupper(trim($valor));
        if (!preg_match('/^PAC-\d{5}$/', $valor)) {
            throw new ReglaDominioException('RN-01', "Código de paciente no válido: «{$valor}» (formato PAC-00000)");
        }
        $this->valor = $valor;
    }
}

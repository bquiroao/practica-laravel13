<?php

declare(strict_types=1);

namespace Prescripciones\Adaptadores\EnMemoria;

use Prescripciones\Aplicacion\Puertos\CatalogoMedicamentos;
use Prescripciones\Dominio\Alergia;
use Prescripciones\Dominio\Medicamento;
use Prescripciones\Dominio\VerificadorAlergias;

final class CatalogoEnMemoria implements CatalogoMedicamentos
{
    /** @var array<string, Medicamento> */
    private array $medicamentos = [];

    /**
     * @param list<Medicamento>     $medicamentos
     * @param array<string, string> $gruposDeAlergenos código o nombre del alérgeno => grupo
     */
    public function __construct(array $medicamentos, private readonly array $gruposDeAlergenos)
    {
        foreach ($medicamentos as $m) {
            $this->medicamentos[$m->codigo] = $m;
        }
    }

    public function buscar(string $codigo): ?Medicamento
    {
        return $this->medicamentos[$codigo] ?? null;
    }

    public function grupoDelAlergeno(Alergia $alergia): ?string
    {
        if ($alergia->codigo !== null && isset($this->gruposDeAlergenos[$alergia->codigo])) {
            return $this->gruposDeAlergenos[$alergia->codigo];
        }
        foreach ($this->gruposDeAlergenos as $clave => $grupo) {
            if (VerificadorAlergias::normalizar((string) $clave) === VerificadorAlergias::normalizar((string) $alergia->nombre)) {
                return $grupo;
            }
        }

        return null;
    }
}

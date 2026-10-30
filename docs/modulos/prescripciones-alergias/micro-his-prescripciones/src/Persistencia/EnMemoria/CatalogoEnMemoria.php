<?php

declare(strict_types=1);

namespace Prescripciones\Persistencia\EnMemoria;

use Prescripciones\Aplicacion\Puertos\CatalogoMedicamentos;
use Prescripciones\Dominio\Modelo\Alergia;
use Prescripciones\Dominio\Modelo\Medicamento;
use Prescripciones\Dominio\Reglas\VerificadorAlergias;

final class CatalogoEnMemoria implements CatalogoMedicamentos
{
    /** @var array<string, Medicamento> */
    private array $medicamentos = [];

    /**
     * @param list<Medicamento>     $medicamentos
     * @param array<string, string> $grupos clave del alérgeno en minúsculas y sin tildes => grupo (como allergen_groups)
     */
    public function __construct(array $medicamentos, private readonly array $grupos)
    {
        foreach ($medicamentos as $m) {
            $this->medicamentos[$m->codigo] = $m;
        }
    }

    public function buscar(string $codigo): ?Medicamento
    {
        return $this->medicamentos[$codigo] ?? null;
    }

    /** Mismo orden que PdoCatalogoMedicamentos: primero el código, después el nombre normalizado. */
    public function grupoDelAlergeno(Alergia $alergia): ?string
    {
        $claves = [
            $alergia->codigo !== null ? strtolower(trim($alergia->codigo)) : null,
            $alergia->nombre !== null ? VerificadorAlergias::normalizar($alergia->nombre) : null,
        ];
        foreach ($claves as $clave) {
            if ($clave !== null && isset($this->grupos[$clave])) {
                return $this->grupos[$clave];
            }
        }

        return null;
    }
}

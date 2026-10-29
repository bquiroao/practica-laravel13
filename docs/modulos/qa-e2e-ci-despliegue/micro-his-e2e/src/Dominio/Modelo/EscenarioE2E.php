<?php

declare(strict_types=1);

namespace E2e\Dominio\Modelo;

use E2e\Dominio\Excepciones\ReglaRechazada;

/** Un flujo del HIS que se prueba de punta a punta, con su criticidad y su conjunto de datos SINTÉTICO (R-01). */
final readonly class EscenarioE2E
{
    /** @param list<string> $requisitos */
    public function __construct(
        public string $tenant,
        public string $id,
        public string $titulo,
        public string $modulo,
        public bool $critico,
        public array $requisitos,
        public string $datos,
        public string $autor,
    ) {
    }

    /** @param list<mixed> $requisitos */
    public static function registrar(string $tenant, string $id, string $titulo, string $modulo, bool $critico, array $requisitos,
        string $datos, string $autor): self
    {
        $campos = [];
        if (!preg_match('/^E2E-\d{3,5}$/', $id)) {
            $campos[] = 'id';
        }
        if (trim($titulo) === '' || mb_strlen($titulo) > 200) {
            $campos[] = 'titulo';
        }
        if (!preg_match('/^[a-z][a-z0-9-]{1,40}$/', $modulo)) {
            $campos[] = 'modulo';
        }
        if ($requisitos === [] || array_filter($requisitos, static fn ($r): bool => !is_string($r) || !preg_match('/^RF-\d{2,3}$/', $r)) !== []) {
            $campos[] = 'requisitos';
        }
        if ($campos !== []) {
            throw new ReglaRechazada('DATOS_INVALIDOS', $campos);
        }
        // R-01: la evidencia muestra pantallas completas; el conjunto de datos tiene que ser uno sintético declarado.
        if (!preg_match('/^SINT-[A-Z0-9-]{2,40}$/', $datos)) {
            throw new ReglaRechazada('DATOS_NO_SINTETICOS', ['datos']);
        }
        $requisitos = array_values(array_unique($requisitos));
        sort($requisitos);

        return new self($tenant, $id, trim($titulo), $modulo, $critico, $requisitos, $datos, $autor);
    }
}

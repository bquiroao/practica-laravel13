<?php

declare(strict_types=1);

namespace Qa\Dominio\Puertos;

use Qa\Dominio\Excepciones\ErrorPersistencia;
use Qa\Dominio\Modelo\Defecto;

/** Defectos y su historial (que solo crece). */
interface RepositorioDefectos
{
    /** @return Defecto con id @throws ErrorPersistencia */
    public function crear(Defecto $defecto): Defecto;

    /** Guarda estado, severidad, prioridad y agrega el historial nuevo. @throws ErrorPersistencia */
    public function actualizar(Defecto $defecto): void;

    /** @throws ErrorPersistencia */
    public function porId(string $tenant, int $id): ?Defecto;

    /** @return list<Defecto> @throws ErrorPersistencia */
    public function deCampana(string $tenant, int $campanaId): array;

    /** Defectos CRÍTICOS no cerrados de los casos del alcance (R-09). @param list<string> $casos @throws ErrorPersistencia */
    public function criticosAbiertos(string $tenant, array $casos): int;
}

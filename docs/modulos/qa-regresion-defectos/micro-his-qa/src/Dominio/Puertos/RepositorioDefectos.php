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

    /**
     * Defectos NO cerrados de esos casos, de cualquier campaña (RT-02): son los que el cierre cuenta.
     * @param list<string> $casos @return list<Defecto> @throws ErrorPersistencia
     */
    public function abiertosDe(string $tenant, array $casos): array;

    /** Defectos CRÍTICOS (severidad efectiva) no cerrados de los casos del alcance (R-09). @param list<string> $casos @throws ErrorPersistencia */
    public function criticosAbiertos(string $tenant, array $casos): int;

    /** Defectos MAYOR (severidad efectiva) no cerrados de esos casos (R-15: si los casos son críticos, bloquean el GO). @param list<string> $casos @throws ErrorPersistencia */
    public function mayoresAbiertos(string $tenant, array $casos): int;
}

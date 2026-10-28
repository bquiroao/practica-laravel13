<?php

declare(strict_types=1);

namespace Qa\Dominio\Puertos;

use Qa\Dominio\Excepciones\ErrorPersistencia;
use Qa\Dominio\Modelo\CasoPrueba;

/** Casos de prueba versionados y la matriz de regresión (funcionalidad → casos críticos). Siempre filtrado por tenant. */
interface RepositorioCasos
{
    /** @throws ErrorPersistencia */
    public function guardar(CasoPrueba $caso): void;

    /** Cambia BORRADOR → APROBADO; la base rechaza cualquier otro cambio sobre una versión aprobada. @throws ErrorPersistencia */
    public function aprobar(CasoPrueba $caso): void;

    /** Última versión del caso, o null. @throws ErrorPersistencia */
    public function porId(string $tenant, string $id): ?CasoPrueba;

    /** @param list<string> $ids @return array<string, CasoPrueba> la última versión de cada caso existente @throws ErrorPersistencia */
    public function variosPorId(string $tenant, array $ids): array;

    /** @param list<string> $funcionalidades @return list<string> casos críticos de esas funcionalidades @throws ErrorPersistencia */
    public function criticosDe(string $tenant, array $funcionalidades): array;

    /** @return list<CasoPrueba> casos que cubren el requisito @throws ErrorPersistencia */
    public function queCubren(string $tenant, string $requisito): array;
}

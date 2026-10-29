<?php

declare(strict_types=1);

namespace E2e\Dominio;

/*
 * DIP: las abstracciones las define y las POSEE el nivel alto (la política de calidad), con su vocabulario
 * (ejecución, escenario, evidencia), no el del proveedor (run, check-suite, bucket). Los adaptadores las implementan.
 */

/** De dónde salen los resultados de una ejecución: JUnit de un runner, el JSON de GitLab, la API de GitHub… */
interface FuenteResultados
{
    /** @throws ReglaRechazada EJECUCION_INEXISTENTE */
    public function ejecucion(string $id): Ejecucion;
}

/** El almacén confirma que la evidencia existe con ESA huella (R-05, R-08). */
interface AlmacenEvidencias
{
    public function confirmada(string $sha256): bool;
}

/** La hora, para la regla de antigüedad (R-09e): con un reloj fijo, la regla se puede probar. */
interface Reloj
{
    public function ahora(): \DateTimeImmutable;
}

/** Las evaluaciones se agregan, no se reemplazan. */
interface RepositorioEvaluaciones
{
    public function guardar(Decision $decision): void;

    /** @return list<Decision> */
    public function deEjecucion(string $ejecucion): array;
}

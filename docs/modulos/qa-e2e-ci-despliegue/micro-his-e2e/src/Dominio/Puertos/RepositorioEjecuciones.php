<?php

declare(strict_types=1);

namespace E2e\Dominio\Puertos;

use E2e\Dominio\Excepciones\ErrorPersistencia;
use E2e\Dominio\Modelo\Ejecucion;
use E2e\Dominio\Modelo\ResultadoEscenario;

interface RepositorioEjecuciones
{
    /** @throws ErrorPersistencia */
    public function crear(Ejecucion $ejecucion): Ejecucion;

    /** Solo el estado, la hora de término y el motivo; los resultados se agregan aparte. @throws ErrorPersistencia */
    public function actualizarEstado(Ejecucion $ejecucion): void;

    /** Agrega (nunca reemplaza) un resultado con sus evidencias. @throws ErrorPersistencia */
    public function agregarResultado(string $tenant, int $ejecucion, ResultadoEscenario $resultado): ResultadoEscenario;

    /** @throws ErrorPersistencia */
    public function porId(string $tenant, int $id): ?Ejecucion;

    /** R-12: el resultado ya registrado con esa clave en el tenant, con su ejecución. @return array{0: int, 1: ResultadoEscenario}|null */
    public function resultadoPorClave(string $tenant, string $clave): ?array;

    /** @return list<Ejecucion> @throws ErrorPersistencia */
    public function deVersion(string $tenant, string $version): array;
}

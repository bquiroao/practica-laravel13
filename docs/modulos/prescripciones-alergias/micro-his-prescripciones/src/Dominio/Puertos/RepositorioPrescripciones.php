<?php

declare(strict_types=1);

namespace Prescripciones\Dominio\Puertos;

use Prescripciones\Dominio\Excepciones\ClaveEnUso;
use Prescripciones\Dominio\Excepciones\ErrorPersistencia;
use Prescripciones\Dominio\Excepciones\YaAnulada;
use Prescripciones\Dominio\Modelo\Anulacion;
use Prescripciones\Dominio\Modelo\Prescripcion;

/**
 * Repositorio de prescripciones (puerto del dominio). Sin actualizar() ni eliminar(): una prescripción
 * emitida es inmutable (R-10). Toda búsqueda va acotada al hospital (R-08).
 * Semana 6: anular() AGREGA un hecho (la anulación); no modifica la prescripción. porClave y porId la devuelven con ella.
 */
interface RepositorioPrescripciones
{
    /** @throws ErrorPersistencia */
    public function porClave(string $hospital, string $clave): ?Prescripcion;

    /** @throws ErrorPersistencia */
    public function porId(string $hospital, int $id): ?Prescripcion;

    /**
     * @throws ClaveEnUso        si otra emisión concurrente ya usó la misma Idempotency-Key en el hospital
     * @throws ErrorPersistencia
     */
    public function guardar(Prescripcion $prescripcion): Prescripcion;

    /**
     * @throws YaAnulada         si la prescripción ya tiene una anulación (otra petición ganó la carrera)
     * @throws ErrorPersistencia
     */
    public function anular(Anulacion $anulacion): void;
}

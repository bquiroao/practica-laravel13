<?php

declare(strict_types=1);

namespace Qa\Dominio\Evidencia;

/**
 * Base de los subtipos. Los cuatro métodos del contrato son FINAL: un subtipo no puede debilitar la postcondición de
 * huella() (como hacía EnlaceExterno en el ANTES) ni agregar una excepción a contenido() (como CapturaConDatosClinicos).
 * Un subtipo solo agrega su validación de formato en el constructor y, si quiere, métodos propios (extensión, no cambio).
 */
abstract class EvidenciaInmutable implements EvidenciaVerificable
{
    private readonly string $bytes;
    private readonly string $huella;

    /** @throws EvidenciaInvalida */
    protected function __construct(string $bytes, private readonly Clasificacion $clasificacion)
    {
        if ($bytes === '') {
            throw new EvidenciaInvalida('EVIDENCIA_VACIA');
        }
        $this->bytes = $bytes;
        $this->huella = hash('sha256', $bytes);
    }

    final public function contenido(): string
    {
        return $this->bytes;
    }

    final public function huella(): string
    {
        return $this->huella;
    }

    final public function tamano(): int
    {
        return strlen($this->bytes);
    }

    final public function clasificacion(): Clasificacion
    {
        return $this->clasificacion;
    }
}

<?php

declare(strict_types=1);

namespace Pacientes\Aplicacion\Puertos;

use Pacientes\Aplicacion\Excepciones\ClaveIdempotenciaEnUso;
use Pacientes\Dominio\Excepciones\ErrorPersistencia;

/**
 * Recuerda la respuesta de un registro por (hospital, actor, Idempotency-Key), para que un
 * cliente pueda REINTENTAR un POST tras un timeout sin crear un paciente duplicado.
 * guardar() participa en la misma transacción que el paciente.
 */
interface AlmacenIdempotencia
{
    /**
     * @return array{huella: string, datos: array<string, mixed>}|null
     * @throws ErrorPersistencia
     */
    public function buscar(string $hospital, string $actor, string $clave): ?array;

    /**
     * @param array<string, mixed> $datos respuesta original
     * @throws ClaveIdempotenciaEnUso si la clave ya existe (dos peticiones simultáneas)
     * @throws ErrorPersistencia
     */
    public function guardar(string $hospital, string $actor, string $clave, string $huella, array $datos): void;
}

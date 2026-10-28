<?php

declare(strict_types=1);

namespace Prescripciones\Aplicacion\CasosDeUso;

use Prescripciones\Aplicacion\Puertos\PoliticaAcceso;
use Prescripciones\Aplicacion\Resultados\Resultado;
use Prescripciones\Dominio\Excepciones\ErrorPersistencia;
use Prescripciones\Dominio\Puertos\RepositorioPrescripciones;

/** CU-09: farmacia, enfermería y el médico ven la prescripción con su verificación. */
final class ConsultarPrescripcion
{
    public const PERMISO = 'prescripcion.consultar';

    public function __construct(
        private readonly PoliticaAcceso $acceso,
        private readonly RepositorioPrescripciones $repositorio,
    ) {
    }

    public function ejecutar(string $usuario, string $hospital, int $id): Resultado
    {
        if (!$this->acceso->permite($usuario, self::PERMISO, $hospital)) {
            return new Resultado(Resultado::DENEGADO, 'PERMISO_DENEGADO');
        }
        try {
            $p = $this->repositorio->porId($hospital, $id);
        } catch (ErrorPersistencia) {
            return new Resultado(Resultado::NO_DISPONIBLE, 'ERROR_PERSISTENCIA');
        }

        // Otro hospital o inexistente: la misma respuesta (R-08).
        return $p === null
            ? new Resultado(Resultado::NO_ENCONTRADO, 'NO_ENCONTRADO')
            : new Resultado(Resultado::OK, 'PRESCRIPCION', $p->aArreglo());
    }
}

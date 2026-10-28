<?php

declare(strict_types=1);

namespace Pacientes\Dominio\Servicios;

use Pacientes\Dominio\Excepciones\ErrorDominio;
use Pacientes\Dominio\Modelo\Paciente;

/** R-08 y R-07: quién puede editar según el estado, y motivo obligatorio para la identidad. */
final class PoliticaEdicion
{
    /**
     * @param bool $puedeEditarRestringidos el actor tiene pacientes.editar_restringido
     * @throws ErrorDominio
     */
    public function verificar(Paciente $actual, Paciente $nuevo, bool $puedeEditarRestringidos, string $motivo): void
    {
        if ($actual->estado !== Paciente::ACTIVO && !$puedeEditarRestringidos) {
            throw new ErrorDominio(ErrorDominio::PROHIBIDO, 'REQUIERE_SUPERVISOR');
        }
        if ($nuevo->cambiaIdentidadRespectoDe($actual) && trim($motivo) === '') {
            throw new ErrorDominio(ErrorDominio::INVALIDO, 'MOTIVO_REQUERIDO');
        }
    }
}

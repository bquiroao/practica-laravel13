<?php

declare(strict_types=1);

namespace Pacientes\Dominio\Servicios;

use Pacientes\Dominio\Excepciones\ErrorDominio;
use Pacientes\Dominio\Modelo\Paciente;

/**
 * Semana 6 — R-11 «identidad protegida»: quién puede proteger a un paciente o quitarle la
 * protección (pacientes.proteger, con motivo) y quién puede ver o editar a un protegido
 * (pacientes.leer_protegido). Es una política NUEVA: PoliticaEdicion no se modificó.
 */
final class PoliticaConfidencialidad
{
    /** @throws ErrorDominio PROHIBIDO / INVALIDO */
    public function verificarRegistro(Paciente $paciente, bool $puedeProteger, string $motivo): void
    {
        if ($paciente->identidadProtegida) {
            $this->exigirPermisoYMotivo($puedeProteger, $motivo);
        }
    }

    /**
     * Quien no puede ver a un protegido tampoco puede editarlo: no se edita lo que no se ve.
     * Se verifica ANTES de validar los cambios, para no revelar nada con otros errores.
     *
     * @throws ErrorDominio PROHIBIDO
     */
    public function exigirVisibilidad(Paciente $actual, bool $puedeVerProtegidos): void
    {
        if ($actual->seMuestraReducidoPara($puedeVerProtegidos)) {
            throw new ErrorDominio(ErrorDominio::PROHIBIDO, 'ACCESO_DENEGADO');
        }
    }

    /** @throws ErrorDominio PROHIBIDO / INVALIDO */
    public function verificarCambio(Paciente $actual, Paciente $nuevo, bool $puedeProteger, string $motivo): void
    {
        if ($actual->identidadProtegida !== $nuevo->identidadProtegida) {
            $this->exigirPermisoYMotivo($puedeProteger, $motivo);
        }
    }

    private function exigirPermisoYMotivo(bool $puedeProteger, string $motivo): void
    {
        if (!$puedeProteger) {
            throw new ErrorDominio(ErrorDominio::PROHIBIDO, 'ACCESO_DENEGADO');
        }
        if (trim($motivo) === '') {
            throw new ErrorDominio(ErrorDominio::INVALIDO, 'MOTIVO_REQUERIDO');
        }
    }
}

<?php

declare(strict_types=1);

namespace Pacientes\Aplicacion\Servicios;

use Pacientes\Aplicacion\Puertos\PoliticaAcceso;
use Pacientes\Dominio\Excepciones\ErrorDominio;

/** Permiso por operación (R-05), común a los cuatro casos de uso. */
final class ControlAcceso
{
    public const REGISTRAR = 'pacientes.registrar';
    public const BUSCAR = 'pacientes.buscar';
    public const LEER = 'pacientes.leer';
    public const EDITAR = 'pacientes.editar';
    public const CONFIRMAR_DUPLICADO = 'pacientes.confirmar_duplicado';
    public const EDITAR_RESTRINGIDO = 'pacientes.editar_restringido';

    public function __construct(private readonly PoliticaAcceso $politica)
    {
    }

    /** @throws ErrorDominio PROHIBIDO / ACCESO_DENEGADO */
    public function exigir(string $actor, string $hospital, string $permiso): void
    {
        if (!$this->politica->permite($actor, $hospital, $permiso)) {
            throw new ErrorDominio(ErrorDominio::PROHIBIDO, 'ACCESO_DENEGADO');
        }
    }

    public function tiene(string $actor, string $hospital, string $permiso): bool
    {
        return $this->politica->permite($actor, $hospital, $permiso);
    }
}

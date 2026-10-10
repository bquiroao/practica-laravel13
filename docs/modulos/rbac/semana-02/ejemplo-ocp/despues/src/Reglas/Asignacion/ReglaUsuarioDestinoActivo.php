<?php

declare(strict_types=1);

namespace Rbac\Reglas\Asignacion;

use Rbac\Contratos\DirectorioUsuarios;
use Rbac\Contratos\ReglaAsignacion;
use Rbac\Dominio\ErrorAsignacion;
use Rbac\Dominio\SolicitudAsignacion;

/** D-02 / E-01: el usuario destino debe existir y estar activo. */
final class ReglaUsuarioDestinoActivo implements ReglaAsignacion
{
    public function __construct(private readonly DirectorioUsuarios $usuarios)
    {
    }

    public function evaluar(SolicitudAsignacion $solicitud): ?ErrorAsignacion
    {
        return match ($this->usuarios->estadoDe($solicitud->usuarioDestinoId)) {
            null => new ErrorAsignacion('USUARIO_NO_ENCONTRADO', 404),
            'active' => null,
            default => new ErrorAsignacion('USUARIO_INACTIVO', 422),
        };
    }
}

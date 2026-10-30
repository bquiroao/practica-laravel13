<?php

declare(strict_types=1);

namespace Qa\Aplicacion\Puertos;

use Qa\Dominio\Excepciones\ErrorPersistencia;

interface RegistroAuditoria
{
    /** Sin datos clínicos: el recurso es un identificador (caso:TC-001, campana:3). @throws ErrorPersistencia */
    public function registrar(string $accion, string $tenant, string $usuario, string $recurso): void;
}

<?php

declare(strict_types=1);

namespace Prescripciones\Aplicacion\Puertos;

/** Traza hacia el módulo Auditoría (R-12). El detalle no lleva datos clínicos. */
interface RegistroAuditoria
{
    public function registrar(string $accion, string $hospital, string $usuario, string $recurso, string $resultado): void;
}

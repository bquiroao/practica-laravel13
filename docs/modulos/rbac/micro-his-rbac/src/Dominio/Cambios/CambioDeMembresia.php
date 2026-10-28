<?php

declare(strict_types=1);

namespace Rbac\Dominio\Cambios;

use Rbac\Dominio\Excepciones\ErrorPersistencia;
use Rbac\Dominio\Modelo\EventoAuditoria;
use Rbac\Dominio\Puertos\RepositorioMembresias;

/**
 * Semana 7 — un cambio sobre las membresías que siempre deja evidencia.
 *
 * Cada tipo de cambio sabe cómo aplicarse sobre el puerto y qué evento produce.
 * Agregar un tipo nuevo (p. ej. suspender) es una clase nueva (OCP); quien los
 * aplica (RegistradorDeCambios) no cambia.
 */
interface CambioDeMembresia
{
    /**
     * @return int membresías afectadas (0 = el cambio no tuvo efecto)
     * @throws ErrorPersistencia
     */
    public function aplicarEn(RepositorioMembresias $membresias): int;

    public function evento(int $afectadas): EventoAuditoria;
}

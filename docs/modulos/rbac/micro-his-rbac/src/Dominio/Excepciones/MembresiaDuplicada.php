<?php

declare(strict_types=1);

namespace Rbac\Dominio\Excepciones;

/** R-03 detectado por la base de datos (índice único), por ejemplo en una carrera entre dos solicitudes. */
final class MembresiaDuplicada extends ErrorPersistencia
{
}

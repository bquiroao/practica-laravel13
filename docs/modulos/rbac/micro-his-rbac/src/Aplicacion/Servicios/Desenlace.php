<?php

declare(strict_types=1);

namespace Rbac\Aplicacion\Servicios;

/** Resultado técnico de aplicar un cambio de membresía; cada caso de uso lo traduce a su respuesta. */
enum Desenlace
{
    /** El cambio se aplicó y su evento quedó en la bitácora, en la misma transacción. */
    case APLICADO;
    /** No había nada que cambiar (p. ej. otra solicitud ya revocó): no se registra evento. */
    case SIN_EFECTO;
    /** La base rechazó un duplicado (índice único): carrera entre dos solicitudes. */
    case DUPLICADO;
    /** Falló la persistencia o la bitácora: se revirtió todo. */
    case FALLA;
}

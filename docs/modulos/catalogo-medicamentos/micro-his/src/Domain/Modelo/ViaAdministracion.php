<?php
declare(strict_types=1);

namespace Catalogo\Domain\Modelo;

/** Lista cerrada de vías de administración. Cada valor cabe en Limites::VIA_MAX. */
enum ViaAdministracion: string
{
    case ORAL = 'ORAL';
    case INTRAVENOSA = 'INTRAVENOSA';
    case INTRAMUSCULAR = 'INTRAMUSCULAR';
    case SUBCUTANEA = 'SUBCUTANEA';
    case TOPICA = 'TOPICA';
    case OFTALMICA = 'OFTALMICA';
    case INHALATORIA = 'INHALATORIA';
    case RECTAL = 'RECTAL';
}

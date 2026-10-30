<?php
declare(strict_types=1);

namespace Catalogo\Domain\Modelo;

/** Lista cerrada de formas farmacéuticas. Cada valor cabe en Limites::FORMA_MAX. */
enum FormaFarmaceutica: string
{
    case TABLETA = 'TABLETA';
    case CAPSULA = 'CAPSULA';
    case JARABE = 'JARABE';
    case SUSPENSION = 'SUSPENSION';
    case SOLUCION_ORAL = 'SOLUCION_ORAL';
    case SOLUCION_INYECTABLE = 'SOLUCION_INYECTABLE';
    case POLVO_INYECTABLE = 'POLVO_INYECTABLE';
    case CREMA = 'CREMA';
    case UNGUENTO = 'UNGUENTO';
    case GOTAS_OFTALMICAS = 'GOTAS_OFTALMICAS';
    case INHALADOR = 'INHALADOR';
    case SUPOSITORIO = 'SUPOSITORIO';
}

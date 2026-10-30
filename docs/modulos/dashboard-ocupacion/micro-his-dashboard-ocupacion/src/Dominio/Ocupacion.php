<?php
declare(strict_types=1);

namespace MicroHis\DashboardOcupacion\Dominio;

/** Violación de una regla de negocio. El código (E3) lo traduce a HTTP la capa de presentación. */
final class ReglaDeDominio extends \DomainException
{
    public function __construct(public readonly string $codigo, string $mensaje) { parent::__construct($mensaje); }
}

/** Ocupación de una sala (R-01, R-02, R-04): solo cifras, nunca datos de pacientes (R-07). */
final class OcupacionSala
{
    public const ESTADOS = ['available', 'occupied', 'maintenance', 'inactive'];

    /** @param list<array{cama: string, estado: string, estancias: int}> $camas */
    public static function calcular(array $camas): array
    {
        $n = array_fill_keys(self::ESTADOS, 0); $inconsistentes = [];
        foreach ($camas as $c) {
            $n[$c['estado']]++;
            // R-04: «ocupada» exige exactamente una estancia abierta, y otra cama, ninguna: dos estancias son dos pacientes
            // en una cama (semana 6). Se marca; el tablero no la corrige (R-09).
            if ($c['estancias'] !== ($c['estado'] === 'occupied' ? 1 : 0)) $inconsistentes[] = $c['cama'];
        }
        $operativas = $n['available'] + $n['occupied'];   // mantenimiento e inactivas se muestran aparte
        return $n + ['ocupacion' => $operativas === 0 ? 'no aplica' : round(100 * $n['occupied'] / $operativas, 1), 'inconsistentes' => $inconsistentes];
    }
}

/** Corte consolidado con su hora (R-05): avisa si tiene más de 15 minutos (R-06) y se filtra por estado de cama (CU-03). */
final class Corte
{
    public function __construct(public readonly int $hora, public readonly array $salas) {}

    public function esViejo(int $ahora): bool { return $ahora - $this->hora > 15 * 60; }

    public function filtrar(?string $estado): array
    {
        if ($estado === null) return $this->salas;
        if (!in_array($estado, OcupacionSala::ESTADOS, true)) throw new ReglaDeDominio('E3', 'Ese estado de cama no existe.');
        return array_filter($this->salas, fn (array $s) => $s[$estado] > 0);
    }
}

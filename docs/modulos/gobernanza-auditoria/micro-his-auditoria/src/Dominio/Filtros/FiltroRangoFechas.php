<?php

declare(strict_types=1);

namespace Auditoria\Dominio\Filtros;

use Auditoria\Dominio\Modelo\Movimiento;

/**
 * R-06: rango libre y OBLIGATORIO. Las fechas se escriben en la hora local del hospital;
 * «hasta» incluye el día completo (se compara con el día siguiente, exclusivo).
 */
final class FiltroRangoFechas implements FiltroMovimiento
{
    private readonly \DateTimeZone $zona;

    public function __construct(string $zona = 'America/Guatemala')
    {
        $this->zona = new \DateTimeZone($zona);
    }

    public function parametros(): array
    {
        return ['desde', 'hasta'];
    }

    public function obligatorio(): bool
    {
        return true;
    }

    public function activo(array $p): bool
    {
        return isset($p['desde']) || isset($p['hasta']);
    }

    public function validar(array $p): array
    {
        $desde = $this->fecha($p['desde'] ?? '');
        $hasta = $this->fecha($p['hasta'] ?? '');
        if ($desde === null || $hasta === null) {
            return ['desde' => 'Indique desde y hasta (AAAA-MM-DD).'];
        }

        return $desde > $hasta ? ['desde' => 'Desde no puede ser posterior a hasta.'] : [];
    }

    public function coincide(Movimiento $m, array $p): bool
    {
        [$desde, $hastaExclusivo] = $this->limites($p);

        return $m->ocurridoEn >= $desde && $m->ocurridoEn < $hastaExclusivo;
    }

    public function sql(array $p): array
    {
        [$desde, $hastaExclusivo] = $this->limites($p);
        $utc = new \DateTimeZone('UTC');

        return ['ocurrido_en >= :desde AND ocurrido_en < :hasta_exclusivo', [
            'desde' => $desde->setTimezone($utc)->format('Y-m-d H:i:s'),
            'hasta_exclusivo' => $hastaExclusivo->setTimezone($utc)->format('Y-m-d H:i:s'),
        ]];
    }

    /** @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable} */
    private function limites(array $p): array
    {
        return [$this->fecha($p['desde']), $this->fecha($p['hasta'])->modify('+1 day')];
    }

    private function fecha(string $texto): ?\DateTimeImmutable
    {
        $fecha = \DateTimeImmutable::createFromFormat('!Y-m-d', $texto, $this->zona);

        return $fecha !== false && $fecha->format('Y-m-d') === $texto ? $fecha : null;
    }
}

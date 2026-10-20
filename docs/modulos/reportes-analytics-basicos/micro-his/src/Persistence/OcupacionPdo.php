<?php
declare(strict_types=1);

namespace MicroHis\Persistence;

use MicroHis\Domain\DatosNoDisponibles;
use MicroHis\Domain\Filtros;
use MicroHis\Domain\LectorOcupacion;
use MicroHis\Domain\RegistroOrigen;

/** Adaptador PDO del puerto LectorOcupacion. Siempre prepare() + execute(); solo lectura (RNF-04). */
final class OcupacionPdo implements LectorOcupacion
{
    // Una estancia cuenta si se cruza con el período: empezó antes del día siguiente a «hasta» y no terminó antes de «desde».
    private const EN_PERIODO = 'b.enabled AND s.started_at < CAST(:hasta AS date) + 1 '
        . 'AND (s.ended_at IS NULL OR s.ended_at >= CAST(:desde AS date))';

    public function __construct(private readonly ConexionPdo $conexion)
    {
    }

    public function camasHabilitadas(): int
    {
        return (int) $this->consultar('SELECT count(*) AS total FROM bed WHERE enabled = :habilitada', ['habilitada' => 'true'])[0]['total'];
    }

    public function ocupadasPorSala(Filtros $f): array
    {
        $filas = $this->consultar(
            'SELECT w.name AS sala, count(DISTINCT b.id) AS ocupadas
               FROM bed b JOIN ward w ON w.id = b.ward_id JOIN bed_stay s ON s.bed_id = b.id
              WHERE ' . self::EN_PERIODO . '
              GROUP BY w.name ORDER BY ocupadas DESC, w.name',
            ['desde' => $f->desde, 'hasta' => $f->hasta],
        );
        return array_map('intval', array_column($filas, 'ocupadas', 'sala'));
    }

    public function registrosDeSala(Filtros $f, string $sala): array
    {
        $filas = $this->consultar(
            "SELECT DISTINCT ON (b.code) b.code AS cama, p.code AS paciente,
                    to_char(s.started_at, 'YYYY-MM-DD') AS ingreso, to_char(s.ended_at, 'YYYY-MM-DD') AS egreso
               FROM bed b JOIN ward w ON w.id = b.ward_id JOIN bed_stay s ON s.bed_id = b.id
               JOIN local_patient p ON p.id = s.local_patient_id
              WHERE w.name = :sala AND " . self::EN_PERIODO . '
              ORDER BY b.code, s.started_at DESC',
            ['sala' => $sala, 'desde' => $f->desde, 'hasta' => $f->hasta],
        );
        return array_map(
            static fn (array $fila) => new RegistroOrigen($fila['cama'], $fila['paciente'], $fila['ingreso'], $fila['egreso']),
            $filas,
        );
    }

    /** @return list<array<string,mixed>> */
    private function consultar(string $sql, array $parametros): array
    {
        try {
            $sentencia = $this->conexion->pdo()->prepare($sql);
            $sentencia->execute($parametros);
            return $sentencia->fetchAll();
        } catch (\PDOException $e) {
            throw new DatosNoDisponibles('No se pudieron leer los datos de ocupación', 0, $e);
        }
    }
}
